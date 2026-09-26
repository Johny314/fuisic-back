<?php

namespace Tests\Unit\Tasks;

use App\Enums\TaskType;
use App\Support\Tasks\NumberInput;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NumberInputTest extends TestCase
{
    use BuildsTasks;

    public static function parsed(): array
    {
        return [
            'integer' => ['42', 42.0, null],
            'comma' => ['0,5', 0.5, null],
            'dot' => ['0.5', 0.5, null],
            'no leading zero' => [',5', 0.5, null],
            'trailing separator' => ['5.', 5.0, null],
            'thousands with space' => ['1 000', 1000.0, null],
            'thousands with nbsp' => ["1\u{00A0}000\u{202F}000,25", 1000000.25, null],
            'unicode minus' => ['−3', -3.0, null],
            'hyphen minus with space' => ['- 3', -3.0, null],
            'plus' => ['+2,5', 2.5, null],
            'exponent' => ['1,5e3', 1500.0, null],
            'negative exponent' => ['6.67E-11', 6.67e-11, null],
            'unit' => ['9,8 м/с²', 9.8, 'м/с²'],
            'unit without space' => ['2,5кН', 2.5, 'кН'],
            'thousands and unit' => ['1 000 м', 1000.0, 'м'],
            'edge spaces' => ['  7  ', 7.0, null],
        ];
    }

    #[DataProvider('parsed')]
    public function test_parse(string $answer, float $value, ?string $unit): void
    {
        $this->assertSame(['value' => $value, 'unit' => $unit], NumberInput::parse($answer));
    }

    public static function notNumbers(): array
    {
        return [
            'empty' => [''],
            'spaces' => ['   '],
            'word' => ['много'],
            'sign only' => ['-'],
            'separator only' => [','],
            'bad grouping' => ['1 0000'],
            'two numbers' => ['5 5'],
            'two separators' => ['1.2.3'],
            'mixed separators' => ['1,000.5'],
            'double sign' => ['--3'],
            'overflow' => ['1e999'],
        ];
    }

    #[DataProvider('notNumbers')]
    public function test_parse_rejects(string $answer): void
    {
        $this->assertNull(NumberInput::parse($answer));
    }

    public static function answers(): array
    {
        return [
            // [settings, ответ, верно?]
            'exact' => [['value' => 0.5], '0,5', true],
            'exact other notation' => [['value' => 0.5], '0.50', true],
            'exact wrong' => [['value' => 0.5], '0.51', false],
            'float sum is exact' => [['value' => 0.3], '0.30000000000000004', true],
            'thousands' => [['value' => 1000], '1 000', true],
            'negative' => [['value' => -3], '−3', true],
            'sign matters' => [['value' => -3], '3', false],
            'absolute tolerance inside' => [['value' => 9.8, 'tolerance' => 0.1], '9,85', true],
            'absolute tolerance upper edge' => [['value' => 9.8, 'tolerance' => 0.1], '9,9', true],
            'absolute tolerance lower edge' => [['value' => 9.8, 'tolerance' => 0.1], '9.7', true],
            'absolute tolerance outside' => [['value' => 9.8, 'tolerance' => 0.1], '9.91', false],
            'percent tolerance edge' => [['value' => 2.5, 'tolerance' => 5, 'tolerance_type' => 'percent'], '2,625', true],
            'percent tolerance lower edge' => [['value' => 2.5, 'tolerance' => 5, 'tolerance_type' => 'percent'], '2.375', true],
            'percent tolerance outside' => [['value' => 2.5, 'tolerance' => 5, 'tolerance_type' => 'percent'], '2.63', false],
            'percent of negative value' => [['value' => -200, 'tolerance' => 10, 'tolerance_type' => 'percent'], '-180', true],
            'percent of zero is exact' => [['value' => 0, 'tolerance' => 10, 'tolerance_type' => 'percent'], '0.01', false],
            'tiny values' => [['value' => 6.67e-11, 'tolerance' => 1, 'tolerance_type' => 'percent'], '6,7e-11', true],
            'tiny values outside' => [['value' => 6.67e-11], '6.68e-11', false],
            'empty' => [['value' => 0], '', false],
            'not a number' => [['value' => 5], 'пять', false],
            // единицы
            'unit matches' => [['value' => 9.8, 'units' => ['м/с²']], '9,8 м/с²', true],
            'unit case and spaces' => [['value' => 9.8, 'units' => ['м/с²']], '9.8 М / С²', true],
            'unit caret notation' => [['value' => 9.8, 'units' => ['м/с²']], '9.8 м/с^2', true],
            'unit digit notation' => [['value' => 9.8, 'units' => ['м/с^2']], '9.8 м/с2', true],
            'second allowed unit' => [['value' => 2.5, 'units' => ['кН', 'kN']], '2.5 KN', true],
            'multiplication sign' => [['value' => 3, 'units' => ['Н·м']], '3 Н*м', true],
            'no unit is accepted' => [['value' => 9.8, 'units' => ['м/с²']], '9.8', true],
            'other unit' => [['value' => 9.8, 'units' => ['м/с²']], '9,8 км/ч', false],
            'unit but wrong value' => [['value' => 9.8, 'units' => ['м/с²']], '10 м/с²', false],
            'unit for question without units' => [['value' => 5], '5 м', false],
        ];
    }

    #[DataProvider('answers')]
    public function test_check(array $settings, string $answer, bool $correct): void
    {
        $settings += ['tolerance' => null, 'tolerance_type' => 'absolute', 'units' => []];
        $task = $this->task(TaskType::number, $settings, points: 4);

        $check = $this->check($task, ['value' => $answer]);
        $this->assertSame($correct, $check->isCorrect());
        $this->assertSame($correct ? 4.0 : 0.0, $check->score);
        $this->assertSame(4, $check->maxScore);

        // устаревшее поле answer проверяется так же
        $this->assertSame($correct, $this->check($task, ['answer' => $answer])->isCorrect());
    }

    public function test_numeric_json_value(): void
    {
        $task = $this->task(TaskType::number, ['value' => 1.25, 'tolerance' => null, 'tolerance_type' => 'absolute', 'units' => []]);

        $this->assertTrue($this->check($task, ['value' => 1.25])->isCorrect());
        $this->assertFalse($this->check($task, ['value' => 1])->isCorrect());
        $this->assertTrue($this->check($task, ['answer' => 1.25])->isCorrect());
        $this->assertFalse($this->check($task, [])->isCorrect());
        $this->assertSame('1.25', TaskType::number->definition()->answerText(['value' => 1.25]));
    }

    public function test_migrated_settings_without_optional_keys(): void
    {
        $task = $this->task(TaskType::number, ['value' => 60]);

        $this->assertTrue($this->check($task, ['value' => '60'])->isCorrect());
        $this->assertFalse($this->check($task, ['value' => '60 с'])->isCorrect());
    }

    public function test_normalize_unit(): void
    {
        $this->assertSame('кг·м/с2', NumberInput::normalizeUnit(' Кг × М / С² '));
    }
}
