<?php

namespace App\Http\Resources;

use App\Models\StudyGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StudyGroup
 */
class StudyGroupResource extends JsonResource
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
            'name' => $this->name,
            'course_year' => $this->course_year,
            'academic_year' => $this->academic_year,
            'semester' => $this->semester,
            'archived' => $this->archived_at !== null,
            'students_count' => $this->whenCounted('students'),
            'courses' => CourseResource::collection($this->whenLoaded('courses')),
        ];
    }
}
