<?php

namespace App\Http\Requests;

use App\Models\Subject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSubjectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $subject = $this->subject();

        return $subject === null
            ? $this->user()->can('create', Subject::class)
            : $this->user()->can('update', $subject);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->subject() === null ? 'required' : 'sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:32', Rule::unique('subjects', 'code')->ignore($this->subject())],
        ];
    }

    private function subject(): ?Subject
    {
        return $this->route('subject');
    }
}
