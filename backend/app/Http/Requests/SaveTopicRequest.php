<?php

namespace App\Http\Requests;

use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTopicRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $topic = $this->topic();

        return $topic === null
            ? $this->user()->can('create', [Topic::class, $this->route('subject')])
            : $this->user()->can('update', $topic);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $topic = $this->topic();

        return [
            'title' => [
                $topic === null ? 'required' : 'sometimes', 'string', 'max:255',
                Rule::unique('topics', 'title')->where('subject_id', $this->subjectId())->ignore($topic),
            ],
            'position' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.unique' => 'Bu fanda shu nomli mavzu bor.',
        ];
    }

    private function topic(): ?Topic
    {
        return $this->route('topic');
    }

    private function subjectId(): ?int
    {
        /** @var Subject|null $subject */
        $subject = $this->route('subject');

        return $subject?->id ?? $this->topic()?->subject_id;
    }
}
