<?php

declare(strict_types=1);

namespace App\Domain\Shared\Queue;

use App\Domain\Tenancy\TenantContext;
use Closure;
use RuntimeException;

/**
 * Sets the tenant for one job and always clears it, including when the job fails.
 * Queue workers reuse a process, so a leaked setting would contaminate the next job.
 */
final class ApplyTenantContext
{
    public function handle(object $job, Closure $next): void
    {
        $tenantId = $job->tenantId ?? null;

        if (! is_string($tenantId)) {
            throw new RuntimeException('Tenant-aware jobs require a tenant id from the dispatcher.');
        }

        $context = app(TenantContext::class);
        $context->setTenant($tenantId);

        try {
            $next($job);
        } finally {
            $context->reset();
        }
    }
}
