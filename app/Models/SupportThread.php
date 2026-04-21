<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'admin_id',
        'subject',
        'category',
        'issue_type',
        'priority',
        'status',
        'tx_id',
    ];

    public function messages()
    {
        return $this->hasMany(SupportMessage::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(SupportMessage::class)->latestOfMany();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
