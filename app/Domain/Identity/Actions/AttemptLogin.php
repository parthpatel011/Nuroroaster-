<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Membership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class AttemptLogin
{
    public function __construct(private readonly TenantContext $context) {}

    public function __invoke(string $email, string $password): ?Membership
    {
        $user = User::query()->where('email', mb_strtolower($email))->first();

        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            return null;
        }

        $this->context->setUser((string) $user->id);

        $membership = Membership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('created_at')
            ->first();

        if ($membership === null) {
            $this->context->reset();

            return null;
        }

        $this->context->setTenant((string) $membership->tenant_id);
        $tenant = Tenant::query()->find($membership->tenant_id);

        if ($tenant === null || $tenant->status !== 'active') {
            $this->context->reset();

            return null;
        }

        Auth::login($user);

        return $membership;
    }
}
