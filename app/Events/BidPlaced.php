<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Bid;
use App\Models\User;


class BidPlaced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $bid;
    /**
     * Create a new event instance.
     */
    public function __construct($bid)
    {
        $this->bid = $bid;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */

    public function broadcastOn()
    {
        return new PrivateChannel('auction.' . $this->bid->auction_id);
    }

    public function broadcastWith()
    {
        return [
            'bid' => $this->bid->toArray(),
            'user' => $this->bid->user->only('id', 'name'),
        ];
    }
}
