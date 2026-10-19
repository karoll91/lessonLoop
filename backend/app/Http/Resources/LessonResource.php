<?php

namespace App\Http\Resources;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lesson
 */
class LessonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'sequence' => $this->sequence,
            'question_count' => $this->question_count,
            'status' => $this->status->value,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'group' => $this->whenLoaded('course', fn (): ?string => $this->course->relationLoaded('studyGroup') ? $this->course->studyGroup->name : null),
            'subject' => $this->whenLoaded('course', fn (): ?string => $this->course->relationLoaded('subject') ? $this->course->subject->name : null),
            'asked_count' => $this->whenHas('asked_count'),
            'retry_count' => $this->whenHas('retry_count'),
            'average_score' => $this->whenHas('average_score', fn ($average): ?int => $average === null ? null : (int) round((float) $average)),
            'topic_ids' => $this->whenLoaded('topics', fn (): array => $this->topics->pluck('id')->all()),
            'answers' => AnswerResource::collection($this->whenLoaded('answers')),
        ];
    }
}
