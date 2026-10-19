<?php

namespace App\Policies;

use App\Models\StudyGroup;
use App\Models\User;

class StudyGroupPolicy
{
    public function view(User $user, StudyGroup $group): bool
    {
        return $user->teachesGroup($group->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, StudyGroup $group): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, StudyGroup $group): bool
    {
        return $user->isAdmin();
    }
}
