<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Http\Controllers\Api\RetailerController;
use App\Services\UserCommissionSyncService;

class CommissionTransaction extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function (CommissionTransaction $commissionTransaction) {
            UserCommissionSyncService::syncUserById($commissionTransaction->user_id);
        });

        static::deleted(function (CommissionTransaction $commissionTransaction) {
            UserCommissionSyncService::syncUserById($commissionTransaction->user_id);
        });
    }

    protected $fillable = [
        'original_transaction_id',
        'user_id',
        'wallet_id',
        'commission_type',
        'original_amount',
        'commission_percentage',
        'commission_amount',
        'reference',
        'description',
    ];

    protected $casts = [
        'original_amount' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    /**
     * Generate unique reference for commission transaction
     */
    public static function generateReference(): string
    {
        return 'COMM-' . strtoupper(uniqid()) . '-' . time();
    }

    /**
     * Relationship with the original transaction
     */
    public function originalTransaction()
    {
        return $this->belongsTo(Transaction::class, 'original_transaction_id');
    }

    /**
     * Relationship with the user who received commission
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with the wallet that received commission
     */
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Create commission transactions for a withdrawal
     */
    public static function createCommissionTransactions(Transaction $originalTransaction, array $commissionCalculation): array
    {
        $commissionTransactions = [];
        $withdrawalUser = $originalTransaction->user()->first();

        if (!$withdrawalUser) {
            return $commissionTransactions;
        }

        // Map 'user' role to 'retailer' for commission purposes
        $userRole = $withdrawalUser->role;
        if ($userRole === 'user') {
            $userRole = 'retailer';
        }

        $targetMasterDistributor = self::resolveMasterDistributorForWithdrawalUser($withdrawalUser);
        $adminCommissionRecipient = $targetMasterDistributor ?: User::where('role', 'admin')->first();
        $adminCommissionLabel = $targetMasterDistributor ? 'Master distributor' : 'Admin';

        // Create admin/upstream commission transaction
        if ($commissionCalculation['admin_commission_amount'] > 0) {
            $adminUser = $adminCommissionRecipient;
            if ($adminUser) {
                $adminWallet = $adminUser->wallets()->where('type', 'main')->first();
                if (!$adminWallet) {
                    $adminWallet = $adminUser->wallets()->where('type', 'sub')->first();
                }
                if ($adminWallet) {
                    $adminCommission = self::create([
                        'original_transaction_id' => $originalTransaction->id,
                        'user_id' => $adminUser->id,
                        'wallet_id' => $adminWallet->id,
                        'commission_type' => 'admin',
                        'original_amount' => $commissionCalculation['original_amount'],
                        'commission_percentage' => $commissionCalculation['admin_commission_percentage'],
                        'commission_amount' => $commissionCalculation['admin_commission_amount'],
                        'reference' => self::generateReference(),
                        'description' => "{$adminCommissionLabel} commission from {$withdrawalUser->name}'s withdrawal ({$userRole})",
                    ]);

                    // Update admin wallet balance
                    $adminWallet->balance += $commissionCalculation['admin_commission_amount'];
                    $adminWallet->save();

                    RetailerController::notify(
                        $adminUser->id,
                        'commission_credited',
                        'Commission Credited',
                        'Commission credited to your wallet.',
                        [
                            'commission_transaction_id' => $adminCommission->id,
                            'amount' => (float) $commissionCalculation['admin_commission_amount'],
                        ]
                    );

                    $commissionTransactions[] = $adminCommission;
                }
            }
        }

        // Create distributor commission transaction
        if ($commissionCalculation['distributor_commission_amount'] > 0) {
            $distributorUser = self::resolveDistributorForWithdrawalUser($withdrawalUser);
            if ($distributorUser) {
                $distributorWallet = $distributorUser->wallets()->where('type', 'sub')->first();
                if (!$distributorWallet) {
                    $distributorWallet = $distributorUser->wallets()->where('type', 'main')->first();
                }
                if ($distributorWallet) {
                    $distributorCommission = self::create([
                        'original_transaction_id' => $originalTransaction->id,
                        'user_id' => $distributorUser->id,
                        'wallet_id' => $distributorWallet->id,
                        'commission_type' => 'distributor',
                        'original_amount' => $commissionCalculation['original_amount'],
                        'commission_percentage' => $commissionCalculation['distributor_commission_percentage'],
                        'commission_amount' => $commissionCalculation['distributor_commission_amount'],
                        'reference' => self::generateReference(),
                        'description' => "Distributor commission from {$withdrawalUser->name}'s withdrawal ({$userRole})",
                    ]);

                    // Update distributor wallet balance
                    $distributorWallet->balance += $commissionCalculation['distributor_commission_amount'];
                    $distributorWallet->save();

                    RetailerController::notify(
                        $distributorUser->id,
                        'commission_credited',
                        'Commission Credited',
                        'Commission credited to your wallet.',
                        [
                            'commission_transaction_id' => $distributorCommission->id,
                            'amount' => (float) $commissionCalculation['distributor_commission_amount'],
                        ]
                    );

                    $commissionTransactions[] = $distributorCommission;
                }
            }
        }

        return $commissionTransactions;
    }

    private static function resolveDistributorForWithdrawalUser(User $withdrawalUser): ?User
    {
        // If distributor withdraws and distributor commission is configured, credit self.
        if ($withdrawalUser->role === 'distributor') {
            return $withdrawalUser;
        }

        // For retailer/user, prefer explicit parent distributor mapping.
        if (in_array($withdrawalUser->role, ['retailer', 'user'], true) && $withdrawalUser->distributor_id) {
            return User::where('id', $withdrawalUser->distributor_id)
                ->where('role', 'distributor')
                ->first();
        }

        // Backward-compatible fallback for old data where mapping does not exist yet.
        return User::where('role', 'distributor')->first();
    }

    private static function resolveMasterDistributorForWithdrawalUser(User $withdrawalUser): ?User
    {
        if (!in_array($withdrawalUser->role, ['retailer', 'user'], true)) {
            return null;
        }

        if (!$withdrawalUser->distributor_id) {
            return null;
        }

        $distributor = User::where('id', $withdrawalUser->distributor_id)
            ->where('role', 'distributor')
            ->first();

        if (!$distributor || !$distributor->distributor_id) {
            return null;
        }

        $superDistributor = User::where('id', $distributor->distributor_id)
            ->where('role', 'super_distributor')
            ->first();

        if (!$superDistributor || !$superDistributor->distributor_id) {
            return null;
        }

        return User::where('id', $superDistributor->distributor_id)
            ->where('role', 'master_distributor')
            ->first();
    }
}
