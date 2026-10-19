<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Course;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $course = $this->course();

        return $course === null
            ? $this->user()->can('create', Course::class)
            : $this->user()->can('update', $course);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $course = $this->course();

        if ($course !== null) {
            return [
                'teacher_id' => ['required', 'nullable', 'integer', Rule::exists('users', 'id')],
            ];
        }

        return [
            'study_group_id' => ['required', 'integer', 'exists:study_groups,id'],
            'subject_id' => [
                'required', 'integer', 'exists:subjects,id',
                Rule::unique('courses', 'subject_id')->where('study_group_id', $this->integer('study_group_id')),
            ],
            'teacher_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereIn('role', [UserRole::Teacher->value, UserRole::Admin->value])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_id.unique' => 'Bu fan guruhga allaqachon biriktirilgan.',
        ];
    }

    private function course(): ?Course
    {
        return $this->route('course');
    }
}
