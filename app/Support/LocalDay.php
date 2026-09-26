<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * «День» пользователя для повторений: с 04:00 до 04:00 следующего дня по его часовому поясу.
 * Границы — в UTC, как хранятся даты в БД; `end` не входит в день. В дни перехода
 * на летнее/зимнее время день длится 23 или 25 часов.
 */
final readonly class LocalDay
{
    public const START_HOUR = 4;

    private function __construct(
        /** Локальная дата дня, Y-m-d (для серий дней подряд) */
        public string $date,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    public static function current(string $timezone, ?CarbonInterface $now = null): self
    {
        $local = CarbonImmutable::instance($now ?? CarbonImmutable::now())->setTimezone($timezone);

        // сравниваем по часам на стене, а не вычитанием 4 часов: иначе ломается в день перехода времени
        $day = $local->hour < self::START_HOUR ? $local->subDay() : $local;

        return self::forDate($day->toDateString(), $timezone);
    }

    /** День по локальной дате Y-m-d. */
    public static function forDate(string $date, string $timezone): self
    {
        $start = CarbonImmutable::parse($date, $timezone)->setTime(self::START_HOUR, 0);
        // addDay сохраняет время на стене: 04:00 следующего дня, а не +24 часа
        $end = $start->addDay();

        return new self($start->toDateString(), $start->utc(), $end->utc());
    }

    public function contains(CarbonInterface $moment): bool
    {
        return $moment->greaterThanOrEqualTo($this->start) && $moment->lessThan($this->end);
    }
}
