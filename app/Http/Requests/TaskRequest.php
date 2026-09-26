<?php

namespace App\Http\Requests;

use App\Models\Test\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Правка общих полей вопроса в админке; тип, настройки проверки и варианты — через API.
 */
class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        return [
            // вопрос без условия допустим, только если есть картинка
            'problem_statement' => [
                Rule::requiredIf(fn () => blank(Task::query()->find($this->route('id'))?->image_path)),
                'nullable', 'string', 'max:10000',
            ],
            'points' => ['required', 'integer', 'min:0', 'max:100'],
            'explanation' => ['nullable', 'string', 'max:10000'],
            'shuffle_options' => ['boolean'],
        ];
    }
}
