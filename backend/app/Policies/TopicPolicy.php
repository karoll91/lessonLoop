<?php

namespace App\Policies;

use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

/**
 * Teachers maintain the topics of the subjects they teach.
 */
class TopicPolicy
{
    public function view(User $user, Topic $topic): bool
    {
        return $user->teachesSubject($topic->subject_id);
    }

    public function create(User $user, Subject $subject): bool
    {
        return $user->teachesSubject($subject->id);
    }

    public function update(User $user, Topic $topic): bool
    {
        return $user->teachesSubject($topic->subject_id);
    }

    public function delete(User $user, Topic $topic): bool
    {
        return $user->teachesSubject($topic->subject_id);
    }
}
