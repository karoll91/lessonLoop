<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportStudentsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Student::class, $this->route('group')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Either a file (xlsx, xls, csv, txt) or plain text with one name per line.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required_without:text', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
            'text' => ['required_without:file', 'string', 'max:100000'],
            'dry_run' => ['sometimes', 'boolean'],
        ];
    }
}
