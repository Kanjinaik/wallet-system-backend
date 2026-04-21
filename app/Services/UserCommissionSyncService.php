<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserCommission;

class UserCommissionSyncService
{
    private const ELIGIBLE_ROLES = ['admin', 'master_distributor', 'super_distributor', 'distributor'];

    public static function syncUserById(?int $userId): void
    {
        if (!$userId) {
            return;
        }

        $user = User::find($userId);
        if (!$user || !in_array($user->role, self::ELIGIBLE_ROLES, true)) {
            return;
        }

        $commissionSummary = CommissionSummaryService::forUser($user->id);

        UserCommission::updateOrCreate(
            ['user_id' => $user->id],
            [
                'user_name' => $user->name,
                'agent_id' => $user->agent_id,
                'total_commission' => $commissionSummary['earned'],
                'withdrawal_commission' => $commissionSummary['withdrawn'],
                'available_commission' => $commissionSummary['available'],
            ]
        );
    }
}
