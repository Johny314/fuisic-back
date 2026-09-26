<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BlockUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // право и правило «персонал — только admin» проверяет BlockOperation
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
            'comment' => ['nullable', 'string', 'max:5000'],
            // пусто — бессрочно
            'until' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reason' => 'причина',
            'comment' => 'комментарий',
            'until' => 'срок',
        ];
    }
}
