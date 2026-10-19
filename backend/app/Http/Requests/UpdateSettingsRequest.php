<?php

namespace App\Http\Requests;

use App\Services\SettingsStore;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return collect(SettingsStore::RULES)
            ->map(fn (array $range): array => ['sometimes', 'integer', "min:{$range['min']}", "max:{$range['max']}"])
            ->all();
    }
}
