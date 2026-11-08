<?php

declare(strict_types=1);

namespace App\Domain\Shared\Queue;

interface IdempotentWork
{
    public function idempotencyScope(): string;

    public function idempotencyKey(): string;
}
