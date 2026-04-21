<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'admin_commission',
        'distributor_commission',
        'is_active',
    ];

    protected $casts = [
        'admin_commission' => 'decimal:2',
        'distributor_commission' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

