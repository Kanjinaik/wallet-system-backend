<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'support_thread_id',
        'sender_type',
        'sender_id',
        'message',
        'file_url',
    ];

    public function thread()
    {
        return $this->belongsTo(SupportThread::class, 'support_thread_id');
    }
}
