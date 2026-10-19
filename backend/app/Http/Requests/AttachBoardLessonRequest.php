<?php

namespace App\Http\Requests;

use App\Models\Lesson;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttachBoardLessonRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (! $this->user()->can('control', $this->route('board'))) {
            return false;
        }

        $lesson = Lesson::query()->find($this->integer('lesson_id'));

        return $lesson === null || $this->user()->can('manage', $lesson);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
        ];
    }
}
