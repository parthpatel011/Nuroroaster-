<?php

declare(strict_types=1);

namespace App\Domain\Shifts\Support;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Converts a roster duty date plus a local wall-clock shift into UTC instants.
 * An end time at or before the start time belongs to the next local day.
 * The duty date stays the local start date, including overnight shifts.
 */
final class ShiftWindow
{
    public function __construct(
        public readonly CarbonImmutable $startsAt,
        public readonly CarbonImmutable $endsAt,
        public readonly bool $endsNextDay,
    ) {}

    public static function forDutyDate(
        string $dutyDate,
        string $startTime,
        string $endTime,
        string $timezone,
    ): self {
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Hospital timezone must be an IANA timezone.');
        }

        $zone = new DateTimeZone($timezone);
        $start = CarbonImmutable::parse($dutyDate.' '.$startTime, $zone);
        $end = CarbonImmutable::parse($dutyDate.' '.$endTime, $zone);
        $endsNextDay = $end->lessThanOrEqualTo($start);

        if ($endsNextDay) {
            $end = $end->addDay();
        }

        $startsAt = $start->utc();
        $endsAt = $end->utc();

        if ($endsAt->lessThanOrEqualTo($startsAt) || $startsAt->diffInSeconds($endsAt) > 86400) {
            throw new InvalidArgumentException('A duty must be longer than zero and no more than 24 hours.');
        }

        return new self($startsAt, $endsAt, $endsNextDay);
    }
}
