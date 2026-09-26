<?php

namespace App\Support\Tasks;

class MultipleChoice extends ChoiceQuestion
{
    protected function correctCountIsValid(int $count): bool
    {
        return $count >= 1;
    }

    protected function correctCountMessage(): string
    {
        return 'Отметьте хотя бы один верный вариант';
    }
}
