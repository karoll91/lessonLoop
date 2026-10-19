<?php

namespace App\Services;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Where a student stands in the random queue of one course before a lesson.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class QueueStatus implements Arrayable
{
    public const NEVER_ASKED = 'never_asked';

    public const QUEUE = 'queue';

    public const DEBTOR = 'debtor';

    public const RESTING = 'resting';

    public function __construct(
        public string $state,
        public int $askedCount,
        public ?int $lastScore,
        public ?int $lastSequence,
        public ?int $lastPassSequence,
        public int $restLessonsLeft,
    ) {}

    public function isResting(): bool
    {
        return $this->state === self::RESTING;
    }

    /**
     * @return array{state: string, asked_count: int, last_score: ?int, last_sequence: ?int, rest_lessons_left: int}
     */
    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'asked_count' => $this->askedCount,
            'last_score' => $this->lastScore,
            'last_sequence' => $this->lastSequence,
            'rest_lessons_left' => $this->restLessonsLeft,
        ];
    }
}
