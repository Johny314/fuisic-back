<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Логин для входа без email: латиница, цифры, `_` и `.`, 3–32 символа (без `@` — иначе
 * вход принял бы его за email); уникален без учёта регистра, включая удалённые аккаунты.
 */
class Username implements ValidationRule
{
    public const string PATTERN = '/^[A-Za-z0-9_.]{3,32}$/';

    public function __construct(
        private readonly ?int $ignoreUserId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, $value)) {
            $fail('Логин: 3–32 символа — латинские буквы, цифры, «_» и «.».');

            return;
        }

        $taken = User::withTrashed()
            ->where('username', mb_strtolower($value))
            ->when($this->ignoreUserId, fn ($query, $id) => $query->whereKeyNot($id))
            ->exists();

        if ($taken) {
            $fail('Этот логин уже занят.');
        }
    }
}
