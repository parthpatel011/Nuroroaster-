<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Shifts\Support\ShiftWindow;
use PHPUnit\Framework\TestCase;

class ShiftWindowTest extends TestCase
{
    public function test_day_shift_in_kolkata_is_stored_as_utc(): void
    {
        $window = ShiftWindow::forDutyDate('2026-10-31', '07:00:00', '15:00:00', 'Asia/Kolkata');

        $this->assertFalse($window->endsNextDay);
        $this->assertSame('2026-10-31T01:30:00+00:00', $window->startsAt->toIso8601String());
        $this->assertSame('2026-10-31T09:30:00+00:00', $window->endsAt->toIso8601String());
    }

    public function test_overnight_shift_keeps_the_start_date_and_ends_the_next_day(): void
    {
        $window = ShiftWindow::forDutyDate('2026-10-31', '20:00:00', '08:00:00', 'Asia/Kolkata');

        $this->assertTrue($window->endsNextDay);
        $this->assertSame('2026-10-31T14:30:00+00:00', $window->startsAt->toIso8601String());
        $this->assertSame('2026-11-01T02:30:00+00:00', $window->endsAt->toIso8601String());
    }

    public function test_year_boundary_and_leap_day_overnight_shifts(): void
    {
        $newYear = ShiftWindow::forDutyDate('2026-12-31', '20:00', '08:00', 'Asia/Kolkata');
        $leap = ShiftWindow::forDutyDate('2028-02-29', '20:00', '08:00', 'Asia/Kolkata');

        $this->assertSame('2027-01-01T02:30:00+00:00', $newYear->endsAt->toIso8601String());
        $this->assertSame('2028-03-01T02:30:00+00:00', $leap->endsAt->toIso8601String());
    }

    public function test_equal_clock_times_are_a_24_hour_duty(): void
    {
        $window = ShiftWindow::forDutyDate('2026-06-01', '07:00', '07:00', 'Asia/Kolkata');

        $this->assertTrue($window->endsNextDay);
        $this->assertEquals(86400, $window->startsAt->diffInSeconds($window->endsAt));
    }

    public function test_timezone_is_not_hard_coded_to_india(): void
    {
        $window = ShiftWindow::forDutyDate('2026-07-15', '07:00', '15:00', 'America/New_York');

        $this->assertSame('2026-07-15T11:00:00+00:00', $window->startsAt->toIso8601String());
        $this->assertSame('2026-07-15T19:00:00+00:00', $window->endsAt->toIso8601String());
    }
}
