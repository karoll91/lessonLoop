<?php

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartLessonRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('teach', $this->course());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $course = $this->course();

        return [
            'topic_ids' => ['required', 'array', 'min:1'],
            'topic_ids.*' => ['integer', 'distinct', Rule::exists('topics', 'id')->where('subject_id', $course->subject_id)],
            'question_count' => ['required', 'integer', 'min:1', 'max:100'],
            'absent_student_ids' => ['sometimes', 'array'],
            'absent_student_ids.*' => ['integer', Rule::exists('students', 'id')->where('study_group_id', $course->study_group_id)],
        ];
    }

    public function course(): Course
    {
        return $this->route('course');
    }
}
