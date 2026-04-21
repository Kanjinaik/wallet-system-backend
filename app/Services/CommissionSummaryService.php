<?php

namespace App\Services;

use App\Models\CommissionTransaction;
use App\Models\WithdrawRequest;

class CommissionSummaryService
{
    public static function forUser(int $userId): array
    {
        $earned = (float) CommissionTransaction::where('user_id', $userId)->sum('commission_amount');
        $withdrawn = (float) WithdrawRequest::where('user_id', $userId)
            ->whereIn('status', ['pending', 'approved', 'processed'])
            ->get()
            ->sum(function ($request) {
                $debited = data_get($request->metadata, 'debited_amount');

                return $debited !== null ? (float) $debited : (float) $request->amount;
            });

        return [
            'earned' => round($earned, 2),
            'withdrawn' => round($withdrawn, 2),
            'available' => round(max(0, $earned - $withdrawn), 2),
        ];
    }
}
