<?php

declare(strict_types=1);

namespace App\Domain\Shared\Queue;

use App\Domain\Tenancy\TenantContext;
use Closure;
use RuntimeException;

/**
 * Claims a tenant-scoped idempotency key before the job body runs.
 * A repeated key is treated as already done and the body is skipped.
 */
final class ClaimIdempotency
{
    public function handle(object $job, Closure $next): void
    {
        if (! $job instanceof IdempotentWork) {
            $next($job);

            return;
        }

        $tenantId = app(TenantContext::class)->tenantId();

        if ($tenantId === null) {
            throw new RuntimeException('Idempotency claims require a tenant context.');
        }

        $claimed = app(IdempotencyStore::class)->claim(
            $tenantId,
            $job->idempotencyScope(),
            $job->idempotencyKey(),
        );

        if (! $claimed) {
            return;
        }

        $next($job);
    }
}
