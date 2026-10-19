<?php

namespace App\Http\Resources;

use App\Models\Answer;
use App\Models\AnswerEdit;
use App\Models\QuestionOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Teacher view of an answer: includes the correct answer and why the
 * student was drawn. Never sent to the classroom board.
 *
 * @mixin Answer
 */
class AnswerResource extends JsonResource
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
            'status' => $this->status->value,
            'score' => $this->score,
            'passed' => $this->status->isScored() ? $this->isPassing() : null,
            'chosen_option' => $this->chosen_option,
            'comment' => $this->comment,
            'pick_weight' => $this->pick_weight,
            'pick_reason' => $this->pick_reason,
            'graded_at' => $this->graded_at?->toIso8601String(),
            'editable_until' => $this->editableUntil()?->toIso8601String(),
            'edits' => $this->whenLoaded('edits', fn (): array => $this->edits->map(fn (AnswerEdit $edit): array => [
                'old_score' => $edit->old_score,
                'new_score' => $edit->new_score,
                'old_status' => $edit->old_status->value,
                'reason' => $edit->reason,
                'by' => $edit->user?->name,
                'at' => $edit->created_at?->toIso8601String(),
            ])->all()),
            'student' => $this->whenLoaded('student', fn (): array => [
                'id' => $this->student->id,
                'full_name' => $this->student->full_name,
            ]),
            'question' => $this->whenLoaded('question', fn (): array => [
                'id' => $this->question->id,
                'type' => $this->question->type->value,
                'difficulty' => $this->question->difficulty->value,
                'text' => $this->question->text,
                'answer_text' => $this->question->answer_text,
                'options' => $this->question->options->map(fn (QuestionOption $option): array => [
                    'label' => $option->label,
                    'text' => $option->text,
                    'is_correct' => $option->is_correct,
                ])->values()->all(),
            ]),
        ];
    }
}
