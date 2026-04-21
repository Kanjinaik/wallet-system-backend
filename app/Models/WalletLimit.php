<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'limit_type',
        'max_amount',
        'transaction_count',
        'total_amount',
        'reset_date',
    ];

    protected $casts = [
        'max_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'reset_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function checkLimit($userId, $amount, $limitType)
    {
        $today = now()->toDateString();
        $limit = self::where('user_id', $userId)
            ->where('limit_type', $limitType)
            ->first();

        if (!$limit) {
            return true;
        }

        // Reset limits if needed
        $resetDate = match($limitType) {
            'daily' => $today,
            'monthly' => now()->startOfMonth()->toDateString(),
            'per_transaction' => null,
            default => $today,
        };

        if ($limit->reset_date !== $resetDate && $resetDate) {
            $limit->update([
                'transaction_count' => 0,
                'total_amount' => 0,
                'reset_date' => $resetDate,
            ]);
        }

        if ($limitType === 'per_transaction') {
            return $amount <= $limit->max_amount;
        }

        return ($limit->total_amount + $amount) <= $limit->max_amount;
    }

    public static function updateLimit($userId, $amount, $limitType)
    {
        $today = now()->toDateString();
        $resetDate = match($limitType) {
            'daily' => $today,
            'monthly' => now()->startOfMonth()->toDateString(),
            'per_transaction' => null,
            default => $today,
        };

        $limit = self::where('user_id', $userId)
            ->where('limit_type', $limitType)
            ->first();

        if ($limit) {
            // Reset limits if needed
            if ($limit->reset_date !== $resetDate && $resetDate) {
                $limit->update([
                    'transaction_count' => 1,
                    'total_amount' => $amount,
                    'reset_date' => $resetDate,
                ]);
            } else {
                $limit->update([
                    'transaction_count' => $limit->transaction_count + 1,
                    'total_amount' => $limit->total_amount + $amount,
                ]);
            }
        } else {
            // Create new limit record
            self::create([
                'user_id' => $userId,
                'limit_type' => $limitType,
                'transaction_count' => 1,
                'total_amount' => $amount,
                'reset_date' => $resetDate,
            ]);
        }
    }
}
