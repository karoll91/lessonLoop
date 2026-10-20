<?php

namespace App\Http\Requests;

use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderTopicsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [Topic::class, $this->subject()]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'topic_ids' => ['required', 'array', 'min:1'],
            'topic_ids.*' => ['integer', 'distinct', Rule::exists('topics', 'id')->where('subject_id', $this->route('subject')?->id)],
        ];
    }

    public function subject(): Subject
    {
        return $this->route('subject');
    }
}
