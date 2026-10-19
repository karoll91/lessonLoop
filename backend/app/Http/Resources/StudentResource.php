<?php

namespace App\Http\Resources;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Student
 */
class StudentResource extends JsonResource
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
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'queue' => $this->whenLoaded('queueStatus', fn (): array => $this->queueStatus->toArray()),
        ];
    }
}
