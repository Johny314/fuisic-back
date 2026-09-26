<?php

namespace App\Http\Requests;

use App\Enums\Classifications;
use App\Enums\Difficulty;
use App\Enums\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // only allow updates if the user is logged in
        return backpack_auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', Rule::enum(Subject::class)],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'class' => ['nullable', Rule::enum(Classifications::class)],
            'difficulty' => ['nullable', Rule::enum(Difficulty::class)],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            //
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     */
    public function messages(): array
    {
        return [
            //
        ];
    }
}
