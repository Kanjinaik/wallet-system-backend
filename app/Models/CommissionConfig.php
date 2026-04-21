<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\CommissionOverride;

class CommissionConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'user_role',
        'admin_commission',
        'distributor_commission',
        'is_active',
    ];

    protected $casts = [
        'admin_commission' => 'decimal:2',
        'distributor_commission' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get active commission configuration for a specific user role
     */
    public static function getActiveConfig(string $userRole)
    {
        return self::where('user_role', $userRole)
                   ->where('is_active', true)
                   ->first();
    }

    public static function calculateForUser(User $user, float $amount): array
    {
        $override = CommissionOverride::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($override) {
            $adminCommission = round(($amount * $override->admin_commission) / 100, 2);
            $distributorCommission = round(($amount * $override->distributor_commission) / 100, 2);
            $totalCommission = round($adminCommission + $distributorCommission, 2);
            $netAmount = round($amount - $totalCommission, 2);

            return [
                'original_amount' => $amount,
                'admin_commission_percentage' => (float) $override->admin_commission,
                'distributor_commission_percentage' => (float) $override->distributor_commission,
                'admin_commission_amount' => $adminCommission,
                'distributor_commission_amount' => $distributorCommission,
                'total_commission' => $totalCommission,
                'net_amount' => $netAmount,
                'source' => 'override',
            ];
        }

        $config = self::getActiveConfig($user->role);
        if (!$config) {
            return [
                'original_amount' => $amount,
                'admin_commission_percentage' => 0,
                'distributor_commission_percentage' => 0,
                'admin_commission_amount' => 0,
                'distributor_commission_amount' => 0,
                'total_commission' => 0,
                'net_amount' => $amount,
                'source' => 'default',
            ];
        }

        $calculated = $config->calculateCommission($amount);
        $calculated['source'] = 'default';
        return $calculated;
    }

    /**
     * Calculate commission amounts for a given withdrawal amount
     */
    public function calculateCommission(float $amount): array
    {
        $adminCommission = round(($amount * $this->admin_commission) / 100, 2);
        $distributorCommission = round(($amount * $this->distributor_commission) / 100, 2);
        $totalCommission = round($adminCommission + $distributorCommission, 2);
        $netAmount = round($amount - $totalCommission, 2);

        return [
            'original_amount' => $amount,
            'admin_commission_percentage' => $this->admin_commission,
            'distributor_commission_percentage' => $this->distributor_commission,
            'admin_commission_amount' => $adminCommission,
            'distributor_commission_amount' => $distributorCommission,
            'total_commission' => $totalCommission,
            'net_amount' => $netAmount,
        ];
    }
}
