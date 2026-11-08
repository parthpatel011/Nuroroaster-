<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Concerns;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $tenantId = app(TenantContext::class)->tenantId();

            if ($tenantId === null) {
                throw new RuntimeException('Tenant context is required for this query.');
            }

            $query->where($query->getModel()->getTable().'.tenant_id', $tenantId);
        });

        static::creating(function (Model $model): void {
            $tenantId = app(TenantContext::class)->tenantId();

            if ($tenantId === null) {
                throw new RuntimeException('Tenant context is required for this write.');
            }

            $existing = $model->getAttribute('tenant_id');

            if ($existing === null) {
                $model->setAttribute('tenant_id', $tenantId);

                return;
            }

            if ((string) $existing !== $tenantId) {
                throw new RuntimeException('Cross-tenant write blocked.');
            }
        });
    }
}
