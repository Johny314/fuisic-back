<?php

namespace App\Support\Tasks;

use App\Models\Test\Task;

/**
 * Ввод числа: `settings.value`, допуск `tolerance` (null — точное совпадение) в `tolerance_type`
 * absolute | percent, `units` — допустимые обозначения единиц (пусто — единицы не нужны).
 * Ответ — `value` (число или строка «9,8 м/с²», см. parse()).
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

    public function answerRules(): array
    {
        return ['value' => ['nullable', static function (string $attribute, mixed $value, \Closure $fail) {
            if (! (is_int($value) || is_float($value) || (is_string($value) && mb_strlen($value) <= 100))) {
                $fail('Ответ — число или строка до 100 символов');
            }
        }]];
    }

    public function answerText(array $answer): ?string
    {
        $value = $answer['value'] ?? null;

        return match (true) {
            is_string($value) => $value,
            is_int($value), is_float($value) => self::format($value),
            default => parent::answerText($answer),
        };
    }

    public function check(Task $task, array $answer): AnswerCheck
    {
        $settings = $task->settings ?? [];
        $expected = $settings['value'] ?? null;
        $value = $answer['value'] ?? null;
        $given = is_int($value) || is_float($value) ? ['value' => (float) $value, 'unit' => null] : self::parse($this->answerText($answer) ?? '');

        if ($given === null || ! is_numeric($expected) || ! $this->unitMatches($given['unit'], $settings['units'] ?? [])) {
            return AnswerCheck::incorrect($task->points);
        }

        $expected = (float) $expected;
        $tolerance = (float) ($settings['tolerance'] ?? 0);
        if (($settings['tolerance_type'] ?? 'absolute') === 'percent') {
            $tolerance = abs($expected) * $tolerance / 100;
        }

        // запас на погрешность float, чтобы значение ровно на границе допуска засчитывалось
        $epsilon = 1e-12 * max(abs($expected), abs($given['value']), $tolerance);

        return abs($given['value'] - $expected) <= $tolerance + $epsilon
            ? AnswerCheck::correct($task->points)
            : AnswerCheck::incorrect($task->points);
    }

    /**
     * Число с необязательной единицей: «−1 000,5 м/с²» → [-1000.5, 'м/с²']. Запятая или точка — десятичный
     * разделитель, пробел (в т.ч. неразрывный) — разделитель разрядов по 3 цифры, допустима запись «1,5e3».
     * null — не число.
     *
     * @return array{value: float, unit: ?string}|null
     */
    public static function parse(string $answer): ?array
    {
        $answer = str_replace(["\u{2212}", "\u{2013}"], '-', trim(preg_replace('/[\s\p{Z}]+/u', ' ', $answer) ?? ''));
        $pattern = '/^([+-]?) ?([0-9]{1,3}(?: [0-9]{3})+|[0-9]+)?(?:[.,]([0-9]*))?(?:[eE]([+-]?[0-9]+))?(.*)$/u';
        if (! preg_match($pattern, $answer, $m) || ($m[2] === '' && ($m[3] ?? '') === '')) {
            return null;
        }

        $unit = trim($m[5]);
        // «1 0000», «1.2.3», «5 5», «1,000.5» — не число с единицей
        if ($unit !== '' && preg_match('/^[0-9.,+-]/', $unit)) {
            return null;
        }

        $number = $m[1].(str_replace(' ', '', $m[2]) ?: '0').'.'.($m[3] ?: '0').(($m[4] ?? '') !== '' ? 'e'.$m[4] : '');
        $value = (float) $number;

        return is_finite($value) ? ['value' => $value, 'unit' => $unit === '' ? null : $unit] : null;
    }

    /** Единица для сравнения: без регистра и пробелов, «ё» = «е», «м/с^2» = «м/с²» = «м/с2», «Н*м» = «Н⋅м» = «Н·м». */
    public static function normalizeUnit(string $unit): string
    {
        $unit = str_replace(' ', '', TextInput::normalize($unit));
        $unit = strtr($unit, ['⁰' => '0', '¹' => '1', '²' => '2', '³' => '3', '⁴' => '4', '⁻' => '-', '^' => '', '*' => '·', '⋅' => '·', '×' => '·', '•' => '·']);

        return $unit;
    }

    /**
     * У вопроса без единиц ответ с единицей неверен. С единицами — ответ с другой единицей неверен,
     * без единицы — принимается.
     *
     * @param  list<string>  $units
     */
    private function unitMatches(?string $unit, array $units): bool
    {
        if ($unit === null) {
            return true;
        }

        $unit = self::normalizeUnit($unit);
        foreach ($units as $allowed) {
            if (is_string($allowed) && self::normalizeUnit($allowed) === $unit) {
                return true;
            }
        }

        return false;
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
