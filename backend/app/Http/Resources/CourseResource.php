<?php

namespace App\Http\Resources;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Course
 */
class CourseResource extends JsonResource
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
            'study_group_id' => $this->study_group_id,
            'subject_id' => $this->subject_id,
            'teacher_id' => $this->teacher_id,
            'group' => $this->whenLoaded('studyGroup', fn (): array => ['id' => $this->studyGroup->id, 'name' => $this->studyGroup->name]),
            'subject' => $this->whenLoaded('subject', fn (): array => ['id' => $this->subject->id, 'name' => $this->subject->name, 'code' => $this->subject->code]),
            'teacher' => $this->whenLoaded('teacher', fn (): ?array => $this->teacher === null ? null : ['id' => $this->teacher->id, 'name' => $this->teacher->name]),
            'lessons_count' => $this->whenCounted('lessons'),
        ];
    }
}
