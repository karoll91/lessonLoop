<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\Topic;
use App\Models\User;

/**
 * Teachers maintain the question bank of the subjects they teach.
 */
class QuestionPolicy
{
    public function view(User $user, Question $question): bool
    {
        return $user->teachesSubject($question->topic->subject_id);
    }

    public function create(User $user, Topic $topic): bool
    {
        return $user->teachesSubject($topic->subject_id);
    }

    public function update(User $user, Question $question): bool
    {
        return $user->teachesSubject($question->topic->subject_id);
    }

    public function delete(User $user, Question $question): bool
    {
        return $user->teachesSubject($question->topic->subject_id);
    }
}
