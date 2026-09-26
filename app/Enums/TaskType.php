<?php

namespace App\Enums;

use App\Support\Tasks\MultipleChoice;
use App\Support\Tasks\NumberInput;
use App\Support\Tasks\QuestionType;
use App\Support\Tasks\SingleChoice;
use App\Support\Tasks\TextInput;

/**
 * Тип вопроса теста. Новый тип — case здесь и класс-описание в App\Support\Tasks.
 */
enum TaskType: string
{
    use Arrayable;

    case single = 'single';
    case multiple = 'multiple';
    case text = 'text';
    case number = 'number';

    public function definition(): QuestionType
    {
        return match ($this) {
            self::single => new SingleChoice,
            self::multiple => new MultipleChoice,
            self::text => new TextInput,
            self::number => new NumberInput,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::single => 'Один вариант',
            self::multiple => 'Несколько вариантов',
            self::text => 'Ввод текста',
            self::number => 'Ввод числа',
        };
    }
}
