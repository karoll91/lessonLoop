<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Random queue rules
    |--------------------------------------------------------------------------
    |
    | pass_score: a score below this puts the student back into the queue (R3).
    | cooldown_lessons: a passing student can be drawn again only N lessons
    | later, counted per course (R2). With 4 a student who passed in lesson 1
    | is eligible again in lesson 5.
    |
    */

    'pass_score' => (int) env('LESSONLOOP_PASS_SCORE', 60),

    'cooldown_lessons' => (int) env('LESSONLOOP_COOLDOWN_LESSONS', 4),

    'debtor_weight' => 2,

    'never_asked_weight' => 2,

    'recent_question_lessons' => 4,

    'default_question_count' => (int) env('LESSONLOOP_DEFAULT_QUESTION_COUNT', 8),

    /*
    | A teacher may change a grade this many hours after giving it (admins always).
    */

    'grade_edit_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | Classroom board pairing
    |--------------------------------------------------------------------------
    */

    'pair_code_ttl' => (int) env('LESSONLOOP_PAIR_CODE_TTL', 300),

];
