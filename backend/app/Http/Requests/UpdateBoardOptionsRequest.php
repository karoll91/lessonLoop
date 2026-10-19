<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBoardOptionsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('control', $this->route('board'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'show_student_name' => ['sometimes', 'boolean'],
            'show_score' => ['sometimes', 'boolean'],
            'show_answer' => ['sometimes', 'boolean'],
            'timer_seconds' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:600'],
        ];
    }
}
