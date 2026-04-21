<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CommissionTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['super_distributor', 'master_distributor'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $fromDate = $request->query('fromDate');
        $toDate = $request->query('toDate');
        $distributorId = $request->query('distributorId', 'all');
        $retailerId = $request->query('retailerId', 'all');
        $statusFilter = strtolower($request->query('status', 'all'));

        $ownedDistributorIds = $this->ownedDistributorsQuery($user)->pluck('id');
        if ($distributorId !== 'all' && !$ownedDistributorIds->contains((int) $distributorId)) {
            return response()->json(['message' => 'Invalid distributor'], 422);
        }

        $targetDistributorIds = $distributorId === 'all'
            ? $ownedDistributorIds
            : collect([(int) $distributorId]);

        $ownedRetailerIds = User::where('role', 'retailer')
            ->whereIn('distributor_id', $targetDistributorIds)
            ->pluck('id');

        if ($retailerId !== 'all' && !$ownedRetailerIds->contains((int) $retailerId)) {
            return response()->json(['message' => 'Invalid retailer'], 422);
        }

        $targetRetailerIds = $retailerId === 'all'
            ? $ownedRetailerIds
            : collect([(int) $retailerId]);

        $statusGroups = [
            'success' => ['success', 'completed', 'approved', 'processed'],
            'pending' => ['pending', 'processing', 'initiated'],
            'failed' => ['failed', 'rejected', 'cancelled', 'declined', 'error'],
        ];

        $txQuery = Transaction::with(['user.distributor'])
            ->where(function ($q) use ($targetDistributorIds, $targetRetailerIds, $user) {
                $q->whereIn('user_id', $targetDistributorIds)
                    ->orWhereIn('user_id', $targetRetailerIds)
                    ->orWhere('user_id', $user->id);
            });

        if ($fromDate) {
            $txQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $txQuery->whereDate('created_at', '<=', $toDate);
        }
        if ($statusFilter !== 'all' && isset($statusGroups[$statusFilter])) {
            $txQuery->whereIn('status', $statusGroups[$statusFilter]);
        }

        $transactions = $txQuery
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $commissionQuery = CommissionTransaction::with(['originalTransaction.user.distributor'])
            ->where('user_id', $user->id);

        if ($fromDate) {
            $commissionQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $commissionQuery->whereDate('created_at', '<=', $toDate);
        }
        if ($distributorId !== 'all') {
            $commissionQuery->whereHas('originalTransaction.user', function ($q) use ($targetDistributorIds) {
                $q->whereIn('id', $targetDistributorIds)
                    ->orWhereIn('distributor_id', $targetDistributorIds);
            });
        }
        if ($retailerId !== 'all') {
            $commissionQuery->whereHas('originalTransaction', function ($q) use ($targetRetailerIds) {
                $q->whereIn('user_id', $targetRetailerIds);
            });
        }

        $commissionTransactions = $commissionQuery->limit(500)->get();

        $summary = [
            'total_deposits' => (float) $transactions->where('type', 'deposit')->sum('amount'),
            'total_withdrawals' => (float) $transactions->whereIn('type', ['withdraw', 'withdrawal'])->sum('amount'),
            'total_transactions' => $transactions->count(),
            'total_commission' => (float) $commissionTransactions->sum('commission_amount'),
        ];

        $earningsSeries = [];
        foreach ($transactions->where('type', 'deposit') as $tx) {
            $dateKey = Carbon::parse($tx->created_at)->format('Y-m-d');
            $earningsSeries[$dateKey] = ($earningsSeries[$dateKey] ?? 0) + (float) $tx->amount;
        }
        $earnings = collect($earningsSeries)
            ->sortKeys()
            ->map(fn ($amount, $date) => ['date' => $date, 'amount' => $amount])
            ->values();

        $distributorPerformance = [];
        foreach ($transactions->where('type', 'deposit') as $tx) {
            $userModel = $tx->user;
            $distributorName = $userModel?->role === 'distributor'
                ? $userModel->name
                : ($userModel?->distributor?->name ?? 'Unmapped');
            $distributorPerformance[$distributorName] = ($distributorPerformance[$distributorName] ?? 0) + (float) $tx->amount;
        }
        $distributorPerformance = collect($distributorPerformance)
            ->map(fn ($amount, $name) => ['name' => $name, 'deposit' => $amount])
            ->sortByDesc('deposit')
            ->values();

        $distributorOptions = User::whereIn('id', $ownedDistributorIds)
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values();

        $retailerOptions = User::whereIn('id', $ownedRetailerIds)
            ->get(['id', 'name', 'distributor_id'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'distributor_id' => $u->distributor_id])
            ->values();

        $tableRows = $transactions->map(function ($tx) use ($commissionTransactions) {
            $commission = $commissionTransactions->firstWhere('original_transaction_id', $tx->id);
            $user = $tx->user;
            $distributor = $user?->role === 'distributor' ? $user : $user?->distributor;
            return [
                'id' => $tx->id,
                'date' => $tx->created_at,
                'distributor' => $distributor?->name ?? '-',
                'retailer' => $user?->role === 'retailer' ? $user->name : '-',
                'amount' => (float) $tx->amount,
                'commission' => (float) ($commission->commission_amount ?? 0),
                'status' => $tx->status,
                'reference' => $tx->reference,
            ];
        });

        return response()->json([
            'summary' => $summary,
            'earnings' => $earnings,
            'distributor_performance' => $distributorPerformance,
            'rows' => $tableRows,
            'filters' => [
                'distributors' => $distributorOptions,
                'retailers' => $retailerOptions,
            ],
        ]);
    }

    private function ownedDistributorsQuery(User $user)
    {
        if ($user->role === 'super_distributor') {
            return User::where('role', 'distributor')->where('distributor_id', $user->id);
        }
        // master distributor owns super distributors
        return User::where('role', 'super_distributor')->where('distributor_id', $user->id);
    }
}
