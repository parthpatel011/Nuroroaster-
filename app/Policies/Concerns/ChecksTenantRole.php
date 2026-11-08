<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Domain\Tenancy\Support\TenantRole;
use App\Domain\Tenancy\TenantContext;

trait ChecksTenantRole
{
    protected function role(): ?TenantRole
    {
        return app(TenantContext::class)->role();
    }
}
