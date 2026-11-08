<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Middleware;

use App\Domain\Tenancy\Models\Membership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class SetTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $membershipId = $request->session()->get('membership_id');

        if (! is_string($membershipId) || ! Str::isUuid($membershipId)) {
            abort(403);
        }

        $this->context->setUser((string) $user->getAuthIdentifier());

        try {
            $membership = Membership::query()
                ->whereKey($membershipId)
                ->where('user_id', $user->getAuthIdentifier())
                ->where('status', 'active')
                ->first();

            if ($membership === null) {
                abort(403);
            }

            $this->context->setTenant((string) $membership->tenant_id);
            $this->context->setMembership($membership);

            $tenant = Tenant::query()->find($membership->tenant_id);

            if ($tenant === null || $tenant->status !== 'active') {
                abort(403);
            }

            return $next($request);
        } finally {
            $this->context->reset();
        }
    }
}
