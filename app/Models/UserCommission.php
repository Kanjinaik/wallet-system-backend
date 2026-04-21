<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCommission extends Model
{
    use HasFactory;

    protected $table = 'users_commission';

    protected $fillable = [
        'user_id',
        'user_name',
        'agent_id',
        'total_commission',
        'withdrawal_commission',
        'available_commission',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'total_commission' => 'float',
        'withdrawal_commission' => 'float',
        'available_commission' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
