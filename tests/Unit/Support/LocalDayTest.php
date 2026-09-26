<?php

namespace Tests\Unit\Support;

use App\Support\LocalDay;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LocalDayTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string, string, string}>
     *                                                                      [момент UTC, пояс, локальная дата дня, начало UTC, конец UTC]
     */
    public static function moments(): array
    {
        return [
            'UTC after 04:00' => ['2026-09-26 10:00:00', 'UTC', '2026-09-26', '2026-09-26 04:00:00', '2026-09-27 04:00:00'],
            'UTC before 04:00 — previous day' => ['2026-09-26 03:59:59', 'UTC', '2026-09-25', '2026-09-25 04:00:00', '2026-09-26 04:00:00'],
            'UTC exactly 04:00 — new day' => ['2026-09-26 04:00:00', 'UTC', '2026-09-26', '2026-09-26 04:00:00', '2026-09-27 04:00:00'],
            'Moscow +3, 03:59 local' => ['2026-09-26 00:59:00', 'Europe/Moscow', '2026-09-25', '2026-09-25 01:00:00', '2026-09-26 01:00:00'],
            'Moscow +3, 04:00 local' => ['2026-09-26 01:00:00', 'Europe/Moscow', '2026-09-26', '2026-09-26 01:00:00', '2026-09-27 01:00:00'],
            'Moscow, 01:00 local after midnight — still previous day' => ['2026-09-25 22:00:00', 'Europe/Moscow', '2026-09-25', '2026-09-25 01:00:00', '2026-09-26 01:00:00'],
            'New York −4, local evening' => ['2026-09-27 02:00:00', 'America/New_York', '2026-09-26', '2026-09-26 08:00:00', '2026-09-27 08:00:00'],
            'Kamchatka +12, local date ahead of UTC' => ['2026-09-26 17:00:00', 'Asia/Kamchatka', '2026-09-27', '2026-09-26 16:00:00', '2026-09-27 16:00:00'],
            'India +5:30' => ['2026-09-25 22:29:00', 'Asia/Kolkata', '2026-09-25', '2026-09-24 22:30:00', '2026-09-25 22:30:00'],
            // Берлин: 29.03.2026 в 02:00 CET часы переводятся на 03:00 CEST — день 23 часа
            'Berlin spring forward, 03:30 CEST — still previous day' => ['2026-03-29 01:30:00', 'Europe/Berlin', '2026-03-28', '2026-03-28 03:00:00', '2026-03-29 02:00:00'],
            'Berlin spring forward, 04:00 CEST' => ['2026-03-29 02:00:00', 'Europe/Berlin', '2026-03-29', '2026-03-29 02:00:00', '2026-03-30 02:00:00'],
            'Berlin spring forward, 04:30 CEST' => ['2026-03-29 02:30:00', 'Europe/Berlin', '2026-03-29', '2026-03-29 02:00:00', '2026-03-30 02:00:00'],
            // 25.10.2026 в 03:00 CEST часы переводятся на 02:00 CET — день 25 часов
            'Berlin fall back, second 02:30 (CET)' => ['2026-10-25 01:30:00', 'Europe/Berlin', '2026-10-24', '2026-10-24 02:00:00', '2026-10-25 03:00:00'],
            'Berlin fall back, 04:00 CET' => ['2026-10-25 03:00:00', 'Europe/Berlin', '2026-10-25', '2026-10-25 03:00:00', '2026-10-26 03:00:00'],
        ];
    }

    #[DataProvider('moments')]
    public function test_day_bounds(string $nowUtc, string $timezone, string $date, string $start, string $end): void
    {
        $day = LocalDay::current($timezone, CarbonImmutable::parse($nowUtc, 'UTC'));

        $this->assertSame($date, $day->date);
        $this->assertSame($start, $day->start->toDateTimeString());
        $this->assertSame($end, $day->end->toDateTimeString());
        $this->assertSame('UTC', $day->start->timezoneName);
        $this->assertTrue($day->contains(CarbonImmutable::parse($nowUtc, 'UTC')));
    }

    public function test_day_length_changes_on_dst_transitions(): void
    {
        $spring = LocalDay::forDate('2026-03-28', 'Europe/Berlin');
        $fall = LocalDay::forDate('2026-10-24', 'Europe/Berlin');
        $plain = LocalDay::forDate('2026-09-26', 'Europe/Berlin');

        $this->assertSame(23.0, $spring->start->diffInHours($spring->end));
        $this->assertSame(25.0, $fall->start->diffInHours($fall->end));
        $this->assertSame(24.0, $plain->start->diffInHours($plain->end));
    }

    public function test_consecutive_days_have_no_gaps(): void
    {
        $now = CarbonImmutable::parse('2026-03-27 12:00:00', 'UTC');
        $day = LocalDay::current('Europe/Berlin', $now);

        for ($i = 0; $i < 5; $i++) {
            $next = LocalDay::current('Europe/Berlin', $day->end);
            $this->assertTrue($next->start->equalTo($day->end));
            $this->assertFalse($day->contains($day->end));
            $day = $next;
        }
    }

    public function test_input_timezone_does_not_matter(): void
    {
        $utc = LocalDay::current('Europe/Berlin', CarbonImmutable::parse('2026-09-26 10:00:00', 'UTC'));
        $tokyo = LocalDay::current('Europe/Berlin', CarbonImmutable::parse('2026-09-26 19:00:00', 'Asia/Tokyo'));

        $this->assertEquals($utc, $tokyo);
    }
}
