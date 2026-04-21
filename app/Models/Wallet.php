<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'balance',
        'is_frozen',
        'freeze_reason',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'is_frozen' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fromTransactions()
    {
        return $this->hasMany(Transaction::class, 'from_wallet_id');
    }

    public function toTransactions()
    {
        return $this->hasMany(Transaction::class, 'to_wallet_id');
    }

    public function scheduledTransfersFrom()
    {
        return $this->hasMany(ScheduledTransfer::class, 'from_wallet_id');
    }

    public function scheduledTransfersTo()
    {
        return $this->hasMany(ScheduledTransfer::class, 'to_wallet_id');
    }
}
