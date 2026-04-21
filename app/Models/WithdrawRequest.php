<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\UserCommissionSyncService;

class WithdrawRequest extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function (WithdrawRequest $withdrawRequest) {
            UserCommissionSyncService::syncUserById($withdrawRequest->user_id);
        });

        static::deleted(function (WithdrawRequest $withdrawRequest) {
            UserCommissionSyncService::syncUserById($withdrawRequest->user_id);
        });
    }

    protected $fillable = [
        'user_id',
        'wallet_id',
        'amount',
        'net_amount',
        'status',
        'remarks',
        'reviewed_by',
        'reviewed_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
