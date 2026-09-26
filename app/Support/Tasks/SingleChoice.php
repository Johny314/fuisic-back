<?php

namespace App\Support\Tasks;

class SingleChoice extends ChoiceQuestion
{
    protected function correctCountIsValid(int $count): bool
    {
        return $count === 1;
    }

    protected function correctCountMessage(): string
    {
        return 'Отметьте ровно один верный вариант';
    }
}
