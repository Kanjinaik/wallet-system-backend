<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_user_id',
        'user_id',
        'wallet_id',
        'type',
        'amount',
        'reference',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public static function generateReference(): string
    {
        return 'ADJ-' . strtoupper(uniqid()) . '-' . time();
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}

