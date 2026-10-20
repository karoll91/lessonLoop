<?php

namespace App\Services;

use App\Exceptions\LessonFlowException;
use App\Models\Answer;
use App\Models\SyncOperation;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Applies grades and skips the phone made, each exactly once per
 * client_uuid. Used by the online grade/skip calls and by POST /sync, which
 * replays what the phone queued while it had no connection.
 */
class OfflineSync
{
    public function __construct(private LessonRunner $runner) {}

    /**
     * Replay queued operations in the order they were made. A failed one is
     * reported and does not stop the rest.
     *
     * @param  array<int, array<string, mixed>>  $operations
     * @return array<int, array{operation: SyncOperation, duplicate: bool}>
     */
    public function replay(User $user, array $operations): array
    {
        return array_map(function (array $operation) use ($user): array {
            $existing = $this->find($user, $operation['client_uuid']);
            if ($existing !== null) {
                return ['operation' => $existing, 'duplicate' => true];
            }

            $answer = Answer::query()->find($operation['answer_id']);

            return ['operation' => $this->apply($user, $operation, $answer), 'duplicate' => false];
        }, $operations);
    }

    public function find(User $user, ?string $clientUuid): ?SyncOperation
    {
        if ($clientUuid === null) {
            return null;
        }

        return SyncOperation::query()->where('user_id', $user->id)->where('client_uuid', $clientUuid)->first();
    }

    /**
     * Apply one operation and remember its result. Errors a retry cannot fix
     * (answer already closed, no access) are stored as failed.
     *
     * @param  array<string, mixed>  $operation  type (grade|skip), client_uuid, score, chosen_option, comment, reason, performed_at
     */
    public function apply(User $user, array $operation, ?Answer $answer): SyncOperation
    {
        $performedAt = $this->performedAt($operation['performed_at'] ?? null, $answer);
        $record = [
            'user_id' => $user->id,
            'client_uuid' => $operation['client_uuid'],
            'type' => $operation['type'],
            'answer_id' => $answer?->id,
            'performed_at' => $performedAt,
        ];

        try {
            if ($answer === null) {
                throw new LessonFlowException('Javob topilmadi (dars yakunlangan bo\'lishi mumkin).', 'answer_not_found');
            }
            if (Gate::forUser($user)->denies('manage', $answer->lesson)) {
                throw new LessonFlowException('Bu dars sizga tegishli emas.', 'forbidden');
            }

            match ($operation['type']) {
                'grade' => $this->runner->grade(
                    $answer,
                    isset($operation['score']) ? (int) $operation['score'] : null,
                    $operation['comment'] ?? null,
                    $operation['chosen_option'] ?? null,
                    $performedAt,
                ),
                'skip' => ($operation['reason'] ?? null) === 'absent'
                    ? $this->runner->markAbsent($answer)
                    : $this->runner->markNoAnswer($answer, $operation['comment'] ?? null, $performedAt),
            };

            return SyncOperation::query()->create($record + ['status' => SyncOperation::APPLIED]);
        } catch (LessonFlowException $exception) {
            return SyncOperation::query()->create($record + [
                'status' => SyncOperation::FAILED,
                'error_code' => $exception->errorCode,
                'message' => $exception->getMessage(),
            ]);
        } catch (ValidationException $exception) {
            return SyncOperation::query()->create($record + [
                'status' => SyncOperation::FAILED,
                'error_code' => 'invalid',
                'message' => collect($exception->errors())->flatten()->first(),
            ]);
        }
    }

    /**
     * When the teacher actually graded, but never in the future and never
     * before the student was drawn.
     */
    private function performedAt(?string $value, ?Answer $answer): CarbonInterface
    {
        $now = now();
        if ($value === null) {
            return $now;
        }

        $at = Carbon::parse($value);
        if ($at->gt($now)) {
            return $now;
        }

        return $answer?->created_at !== null && $at->lt($answer->created_at) ? $answer->created_at : $at;
    }
}
