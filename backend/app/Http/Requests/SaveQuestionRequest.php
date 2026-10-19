<?php

namespace App\Http\Requests;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\Question;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveQuestionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $question = $this->question();

        return $question === null
            ? $this->user()->can('create', [Question::class, $this->route('topic')])
            : $this->user()->can('update', $question);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $question = $this->question();
        $required = $question === null ? 'required' : 'sometimes';

        return [
            'type' => [$required, Rule::enum(QuestionType::class)],
            'text' => [$required, 'string', 'max:2000'],
            'answer_text' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'difficulty' => ['sometimes', Rule::enum(Difficulty::class)],
            'topic_id' => $question === null ? ['prohibited'] : [
                'sometimes', 'integer',
                Rule::exists('topics', 'id')->where('subject_id', $question->topic->subject_id),
            ],
            'options' => ['sometimes', 'array', 'min:2', 'max:4'],
            'options.*.label' => ['required', 'distinct', 'in:A,B,C,D'],
            'options.*.text' => ['required', 'string', 'max:500'],
            'options.*.is_correct' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A test question needs options with exactly one correct answer.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->effectiveType() !== QuestionType::Test) {
                    return;
                }

                $options = $this->input('options');

                if ($options === null) {
                    if ($this->question()?->options()->exists() !== true) {
                        $validator->errors()->add('options', 'Test savoli uchun variantlar kerak.');
                    }

                    return;
                }

                $correctCount = collect($options)->filter(fn ($option): bool => filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN))->count();

                if ($correctCount !== 1) {
                    $validator->errors()->add('options', 'Aynan bitta variant to\'g\'ri deb belgilanishi kerak.');
                }
            },
        ];
    }

    public function effectiveType(): QuestionType
    {
        return $this->enum('type', QuestionType::class) ?? $this->question()?->type ?? QuestionType::Open;
    }

    private function question(): ?Question
    {
        return $this->route('question');
    }
}
