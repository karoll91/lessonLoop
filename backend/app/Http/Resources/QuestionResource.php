<?php

namespace App\Http\Resources;

use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Question bank view for teachers and admins, including the correct answer.
 *
 * @mixin Question
 */
class QuestionResource extends JsonResource
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
            'topic_id' => $this->topic_id,
            'type' => $this->type->value,
            'difficulty' => $this->difficulty->value,
            'text' => $this->text,
            'answer_text' => $this->answer_text,
            'image_path' => $this->image_path,
            'has_answer' => $this->type === QuestionType::Test || filled($this->answer_text),
            'options' => $this->whenLoaded('options', fn (): array => $this->options->map(fn (QuestionOption $option): array => [
                'label' => $option->label,
                'text' => $option->text,
                'is_correct' => $option->is_correct,
            ])->values()->all()),
            'times_asked' => $this->whenCounted('answers'),
            'average_score' => $this->whenAggregated('answers', 'score', 'avg', fn ($average) => $average === null ? null : (int) round((float) $average)),
        ];
    }
}
