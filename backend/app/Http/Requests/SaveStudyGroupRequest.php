<?php

namespace App\Http\Requests;

use App\Models\StudyGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStudyGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $group = $this->group();

        return $group === null
            ? $this->user()->can('create', StudyGroup::class)
            : $this->user()->can('update', $group);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $group = $this->group();
        $required = $group === null ? 'required' : 'sometimes';

        return [
            'name' => [
                $required, 'string', 'max:50',
                Rule::unique('study_groups', 'name')
                    ->where('academic_year', $this->input('academic_year', $group?->academic_year))
                    ->ignore($group),
            ],
            'course_year' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:6'],
            'academic_year' => ['sometimes', 'nullable', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['sometimes', 'nullable', 'integer', 'in:1,2'],
            'archived' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Bu nomli guruh shu o\'quv yilida allaqachon bor.',
        ];
    }

    /**
     * Validated attributes ready for the model.
     *
     * @return array<string, mixed>
     */
    public function modelAttributes(): array
    {
        $data = collect($this->validated())->except('archived')->all();

        if ($this->has('archived')) {
            $data['archived_at'] = $this->boolean('archived') ? now() : null;
        }

        return $data;
    }

    private function group(): ?StudyGroup
    {
        return $this->route('group');
    }
}
