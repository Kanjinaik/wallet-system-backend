<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BalanceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $wallet_id;
    public $new_balance;
    public $user_id;

    public function __construct($wallet_id, $new_balance, $user_id)
    {
        $this->wallet_id = $wallet_id;
        $this->new_balance = $new_balance;
        $this->user_id = $user_id;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->user_id);
    }

    public function broadcastAs()
    {
        return 'balance.update';
    }
}
