<?php

namespace App\Support;

use App\Models\User;

/**
 * Лимиты интервальных повторений — единственное место, где они задаются.
 * Подписка (fuisic-back#23) будет переопределять потолки здесь же по пользователю.
 */
class RepetitionLimits
{
    /** Потолок настройки «новых карточек в день». */
    public const MAX_NEW_CARDS_PER_DAY = 200;

    /** Потолок наборов в «Моих повторениях»; null — без ограничения. */
    public const MAX_SETS_IN_REPETITION = null;

    public function maxNewCardsPerDay(User $user): int
    {
        return self::MAX_NEW_CARDS_PER_DAY;
    }

    /** Сколько новых карточек показывать сегодня: настройка пользователя, но не выше потолка. */
    public function newCardsPerDay(User $user): int
    {
        return min($user->settings->new_cards_per_day, $this->maxNewCardsPerDay($user));
    }

    public function maxSetsInRepetition(User $user): ?int
    {
        return self::MAX_SETS_IN_REPETITION;
    }
}
