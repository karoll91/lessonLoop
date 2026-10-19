<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $model = $this->model();

        return $model === null
            ? $this->user()->can('create', User::class)
            : $this->user()->can('update', $model);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $model = $this->model();
        $required = $model === null ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'login' => [$required, 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'login')->ignore($model)],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($model)],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'password' => ['sometimes', 'string', 'min:8', 'max:255'],
            'blocked' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $model = $this->model();

                if ($model !== null && $model->is($this->user()) && ($this->boolean('blocked') || $this->input('role') === UserRole::Teacher->value)) {
                    $validator->errors()->add('blocked', 'O\'zingizni bloklay yoki admin huquqidan mahrum qila olmaysiz.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'login.regex' => 'Login faqat kichik lotin harflari, raqam, nuqta, chiziqcha va pastki chiziqdan iborat bo\'ladi.',
            'login.unique' => 'Bu login band.',
        ];
    }

    private function model(): ?User
    {
        return $this->route('user');
    }
}
