<?php

namespace App\Events;

use App\Models\BoardSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The paired phone sent a heartbeat. A small event, so the board does not
 * have to rebuild its whole state every 15 seconds just to stay calm.
 */
class BoardControllerSeen implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public BoardSession $board) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel($this->board->broadcastChannelName())];
    }

    public function broadcastAs(): string
    {
        return 'board.controller-seen';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'controller_online' => true,
            'controller_seen_seconds_ago' => 0,
        ];
    }
}
