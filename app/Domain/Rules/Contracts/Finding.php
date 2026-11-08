<?php

declare(strict_types=1);

namespace App\Domain\Rules\Contracts;

final class Finding
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $ruleType,
        public readonly string $severity,
        public readonly string $fingerprint,
        public readonly string $message,
        public readonly array $details = [],
    ) {}
}
