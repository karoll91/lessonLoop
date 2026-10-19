<?php

namespace App\Enums;

/**
 * Status of a single "student was asked a question" record.
 *
 * Pending: drawn by the random picker, not graded yet.
 * Graded: the teacher gave a 0–100 score.
 * NoAnswer: the student did not answer; stored as score 0 and counts as a debt (R8).
 * Absent: the student turned out to be absent; no score, the queue position is kept (R8).
 */
enum AnswerStatus: string
{
    case Pending = 'pending';
    case Graded = 'graded';
    case NoAnswer = 'no_answer';
    case Absent = 'absent';

    /**
     * Whether this status carries a score that counts for the queue rules.
     */
    public function isScored(): bool
    {
        return $this === self::Graded || $this === self::NoAnswer;
    }
}
