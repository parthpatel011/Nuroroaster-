<?php

declare(strict_types=1);

namespace App\Domain\Rules\Integrity;

use App\Domain\Rules\Contracts\Finding;
use App\Domain\Rules\Contracts\Rule;

/**
 * Mirrors the database exclusion constraint. The database remains the authority.
 */
final class DoubleBookingRule implements Rule
{
    public function type(): string
    {
        return 'BR-01';
    }

    public function evaluate(array $context, array $params): array
    {
        $windows = $context['windows'] ?? [];

        if (! is_array($windows)) {
            return [];
        }

        $findings = [];

        foreach ($windows as $index => $window) {
            if (! is_array($window)) {
                continue;
            }

            foreach (array_slice($windows, $index + 1) as $other) {
                if (! is_array($other) || ! $this->overlaps($window, $other)) {
                    continue;
                }

                $findings[] = new Finding(
                    ruleType: $this->type(),
                    severity: 'block',
                    fingerprint: hash('sha256', json_encode([$window, $other]) ?: ''),
                    message: 'Duties overlap.',
                );
            }
        }

        return $findings;
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     */
    private function overlaps(array $left, array $right): bool
    {
        $leftStart = $left['starts_at'] ?? null;
        $leftEnd = $left['ends_at'] ?? null;
        $rightStart = $right['starts_at'] ?? null;
        $rightEnd = $right['ends_at'] ?? null;

        if (! is_string($leftStart) || ! is_string($leftEnd) || ! is_string($rightStart) || ! is_string($rightEnd)) {
            return false;
        }

        return $leftStart < $rightEnd && $rightStart < $leftEnd;
    }
}
