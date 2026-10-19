<?php

namespace App\Services;

use App\Models\Student;

/**
 * A student who may be drawn by the random picker, with the weight and the
 * rule that produced it. The reason is shown to the teacher only.
 */
final readonly class PickCandidate
{
    public const REASON_NEVER_ASKED = 'never_asked';

    public const REASON_DEBTOR = 'debtor';

    public const REASON_QUEUE = 'queue';

    public const REASON_COOLDOWN_RELAXED = 'cooldown_relaxed';

    public function __construct(
        public Student $student,
        public int $weight,
        public string $reason,
    ) {}
}
