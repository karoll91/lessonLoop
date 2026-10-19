<?php

namespace App\Http\Requests;

use App\Enums\BoardCommand;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BoardCommandRequest extends FormRequest
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
            'command' => ['required', Rule::enum(BoardCommand::class)],
        ];
    }

    public function command(): BoardCommand
    {
        return $this->enum('command', BoardCommand::class);
    }
}
