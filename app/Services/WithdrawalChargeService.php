<?php

namespace App\Services;

class WithdrawalChargeService
{
    public static function calculateRetailerWithdrawalCharge(float $withdrawalAmount, string $userRole): float
    {
        if (!in_array($userRole, ['retailer', 'user'], true)) {
            return 0.0;
        }

        if ($withdrawalAmount >= 100.0 && $withdrawalAmount <= 1000.0) {
            return 5.0;
        }
        if ($withdrawalAmount >= 1001.0 && $withdrawalAmount <= 25000.0) {
            return 10.0;
        }
        if ($withdrawalAmount >= 25001.0 && $withdrawalAmount <= 100000.0) {
            return 15.0;
        }
        if ($withdrawalAmount >= 100001.0 && $withdrawalAmount <= 200000.0) {
            return 20.0;
        }

        return 0.0;
    }
}
