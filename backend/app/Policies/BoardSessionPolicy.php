<?php

namespace App\Policies;

use App\Models\BoardSession;
use App\Models\User;

class BoardSessionPolicy
{
    /**
     * Only the phone that paired with the board may control it.
     */
    public function control(User $user, BoardSession $board): bool
    {
        return $board->controller_id === $user->id;
    }
}
