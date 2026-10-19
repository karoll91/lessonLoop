<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    /**
     * Whether the user may run lessons and read the journal of the course.
     */
    public function teach(User $user, Course $course): bool
    {
        return $user->isAdmin() || $course->teacher_id === $user->id;
    }

    public function view(User $user, Course $course): bool
    {
        return $this->teach($user, $course);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Course $course): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->isAdmin();
    }
}
