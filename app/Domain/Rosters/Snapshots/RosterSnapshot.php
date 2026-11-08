<?php

declare(strict_types=1);

namespace App\Domain\Rosters\Snapshots;

/**
 * Shape of a future immutable publish snapshot. Nothing persists this yet.
 *
 * @phpstan-type SnapshotAssignment array{
 *     employee_code: string,
 *     employee_name: string,
 *     shift_code: string,
 *     duty_date: string,
 *     starts_at: string,
 *     ends_at: string
 * }
 */
final class RosterSnapshot
{
    /**
     * @param  list<array<string, mixed>>  $assignments
     */
    public function __construct(
        public readonly string $rosterId,
        public readonly string $wardCode,
        public readonly string $periodStart,
        public readonly string $periodEnd,
        public readonly string $timezone,
        public readonly array $assignments,
    ) {}
}
