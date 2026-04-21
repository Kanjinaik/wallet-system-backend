<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'from_wallet_id',
        'to_wallet_id',
        'amount',
        'description',
        'frequency',
        'scheduled_at',
        'next_execution_at',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'scheduled_at' => 'datetime',
        'next_execution_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fromWallet()
    {
        return $this->belongsTo(Wallet::class, 'from_wallet_id');
    }

    public function toWallet()
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id');
    }

    public function calculateNextExecution()
    {
        $next = match($this->frequency) {
            'daily' => $this->next_execution_at->addDay(),
            'weekly' => $this->next_execution_at->addWeek(),
            'monthly' => $this->next_execution_at->addMonth(),
            'yearly' => $this->next_execution_at->addYear(),
            default => null,
        };

        return $next;
    }
}
