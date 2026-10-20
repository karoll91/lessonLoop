<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncRequest extends FormRequest
{
    /**
     * Access to each answer is checked per operation (OfflineSync).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'operations' => ['required', 'array', 'min:1', 'max:200'],
            'operations.*.client_uuid' => ['required', 'uuid', 'distinct'],
            'operations.*.type' => ['required', 'string', 'in:grade,skip'],
            'operations.*.answer_id' => ['required', 'integer'],
            'operations.*.score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'operations.*.chosen_option' => ['nullable', 'string', 'in:A,B,C,D'],
            'operations.*.comment' => ['nullable', 'string', 'max:1000'],
            'operations.*.reason' => ['nullable', 'required_if:operations.*.type,skip', 'string', 'in:no_answer,absent'],
            'operations.*.performed_at' => ['nullable', 'date'],
        ];
    }
}
