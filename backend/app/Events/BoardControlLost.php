<?php

namespace App\Events;

use App\Models\BoardSession;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Another phone paired with a board, so the previous controller lost it.
 */
class BoardControlLost implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public BoardSession $board,
        public int $previousControllerId,
        public User $newController,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->previousControllerId)];
    }

    public function broadcastAs(): string
    {
        return 'board.control-lost';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'board_id' => $this->board->id,
            'board_name' => $this->board->name,
            'new_controller' => $this->newController->name,
        ];
    }
}
