<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Rules\Integrity\DoubleBookingRule;
use PHPUnit\Framework\TestCase;

class DoubleBookingRuleTest extends TestCase
{
    public function test_half_open_windows_that_only_touch_are_not_overlaps(): void
    {
        $findings = (new DoubleBookingRule)->evaluate([
            'windows' => [
                ['starts_at' => '2026-10-31T01:30:00+00:00', 'ends_at' => '2026-10-31T09:30:00+00:00'],
                ['starts_at' => '2026-10-31T09:30:00+00:00', 'ends_at' => '2026-10-31T17:30:00+00:00'],
            ],
        ], []);

        $this->assertSame([], $findings);
    }

    public function test_overlap_is_a_block_finding(): void
    {
        $findings = (new DoubleBookingRule)->evaluate([
            'windows' => [
                ['starts_at' => '2026-10-31T14:30:00+00:00', 'ends_at' => '2026-11-01T02:30:00+00:00'],
                ['starts_at' => '2026-11-01T01:30:00+00:00', 'ends_at' => '2026-11-01T09:30:00+00:00'],
            ],
        ], []);

        $this->assertCount(1, $findings);
        $this->assertSame('BR-01', $findings[0]->ruleType);
        $this->assertSame('block', $findings[0]->severity);
    }
}
