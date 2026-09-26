<?php

namespace App\Support\Tasks;

use App\Models\Test\Task;

/**
 * Ввод числа: `settings.value`, допуск `tolerance` (null — точное совпадение) в `tolerance_type`
 * absolute | percent, `units` — допустимые обозначения единиц (пусто — единицы не нужны).
 */
class NumberInput extends QuestionType
{
    public const TOLERANCE_TYPES = ['absolute', 'percent'];

    public function prepare(array $input): array
    {
        foreach (['value', 'tolerance'] as $key) {
            if (is_string($input['settings'][$key] ?? null)) {
                $input['settings'][$key] = str_replace(',', '.', trim($input['settings'][$key]));
            }
        }

        return $input;
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.value' => ['required', 'numeric'],
            'settings.tolerance' => ['nullable', 'numeric', 'min:0'],
            'settings.tolerance_type' => ['nullable', 'in:'.implode(',', self::TOLERANCE_TYPES)],
            'settings.units' => ['nullable', 'array', 'max:10'],
            'settings.units.*' => ['required', 'string', 'max:30', 'distinct'],
        ];
    }

    public function settings(array $input): ?array
    {
        $settings = $input['settings'];

        return [
            'value' => self::number($settings['value']),
            'tolerance' => isset($settings['tolerance']) ? self::number($settings['tolerance']) : null,
            'tolerance_type' => $settings['tolerance_type'] ?? 'absolute',
            'units' => array_values($settings['units'] ?? []),
        ];
    }

    public function settingsFromAnswer(string $answer, ?array $current): ?array
    {
        $value = str_replace(',', '.', trim($answer));
        if (! is_numeric($value)) {
            return null;
        }

        return ['value' => self::number($value)] + ($current ?? []) + [
            'tolerance' => null,
            'tolerance_type' => 'absolute',
            'units' => [],
        ];
    }

    public function answer(Task $task): ?string
    {
        $value = $task->settings['value'] ?? null;

        return $value === null ? null : self::format($value);
    }

    public function matches(Task $task, ?string $answer): bool
    {
        $expected = $task->settings['value'] ?? null;
        if ($answer === null || $expected === null) {
            return false;
        }

        // как раньше — точное совпадение; плюс та же запись числа иначе («5.0» = «5»)
        return $answer === self::format($expected)
            || (is_numeric($answer) && (float) $answer === (float) $expected);
    }

    public static function number(int|float|string $value): int|float
    {
        $number = $value + 0;

        return is_float($number) && floor($number) === $number && abs($number) < 1e15 ? (int) $number : $number;
    }

    public static function format(int|float $value): string
    {
        return (string) self::number($value);
    }
}
