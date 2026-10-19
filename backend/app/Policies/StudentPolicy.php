<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\StudyGroup;
use App\Models\User;

/**
 * Teachers read the students of their groups; only admins change the roster.
 */
class StudentPolicy
{
    public function viewAny(User $user, StudyGroup $group): bool
    {
        return $user->teachesGroup($group->id);
    }

    public function view(User $user, Student $student): bool
    {
        return $user->teachesGroup($student->study_group_id);
    }

    public function create(User $user, StudyGroup $group): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Student $student): bool
    {
        return $user->isAdmin();
    }
}
