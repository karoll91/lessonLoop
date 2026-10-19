<?php

namespace App\Events;

use App\Models\BoardSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The classroom board changed what it shows. The board listens on its public
 * token channel; the controlling phone mirrors the same state on its private
 * user channel.
 */
class BoardUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $state
     */
    public function __construct(
        public BoardSession $board,
        public array $state,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [new Channel($this->board->broadcastChannelName())];

        if ($this->board->controller_id !== null) {
            $channels[] = new PrivateChannel('App.Models.User.'.$this->board->controller_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'board.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->state;
    }
}
