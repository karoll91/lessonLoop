<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GradeAnswerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('answer')->lesson);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'score' => ['nullable', 'required_without:chosen_option', 'integer', 'min:0', 'max:100'],
            'chosen_option' => ['nullable', 'string', 'in:A,B,C,D'],
            'comment' => ['nullable', 'string', 'max:1000'],
            // Makes a retried request safe: it is applied only once.
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }
}
