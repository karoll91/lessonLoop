<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

class LessonPolicy
{
    /**
     * Whether the user may see the lesson, including correct answers.
     */
    public function view(User $user, Lesson $lesson): bool
    {
        return $this->manage($user, $lesson);
    }

    /**
     * Whether the user may draw, grade and finish in the lesson.
     */
    public function manage(User $user, Lesson $lesson): bool
    {
        return $user->isAdmin() || $lesson->teacher_id === $user->id;
    }
}
