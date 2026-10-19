<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $student = $this->student();

        return $student === null
            ? $this->user()->can('create', [Student::class, $this->route('group')])
            : $this->user()->can('update', $student);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => [$this->student() === null ? 'required' : 'sometimes', 'string', 'min:3', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'study_group_id' => ['sometimes', 'integer', 'exists:study_groups,id'],
        ];
    }

    private function student(): ?Student
    {
        return $this->route('student');
    }
}
