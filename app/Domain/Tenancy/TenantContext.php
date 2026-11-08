<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Models\Membership;
use App\Domain\Tenancy\Support\TenantRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Request- and job-scoped tenant context.
 *
 * The database setting is session-level so it survives statement boundaries.
 * Callers must reset it before the connection returns to a pool or the next
 * request or job. Row-level security still fails closed if a query runs
 * after reset, because an empty setting is not a valid uuid.
 */
final class TenantContext
{
    private static bool $runtimeRoleChecked = false;

    private ?string $tenantId = null;

    private ?string $userId = null;

    private ?Membership $membership = null;

    public function tenantId(): ?string
    {
        return $this->tenantId;
    }

    public function userId(): ?string
    {
        return $this->userId;
    }

    public function membership(): ?Membership
    {
        return $this->membership;
    }

    public function role(): ?TenantRole
    {
        $role = $this->membership?->role;

        return is_string($role) ? TenantRole::from($role) : $role;
    }

    public function setUser(string $userId): void
    {
        $this->assertUuid($userId);
        $this->assertRuntimeRole();
        $this->userId = $userId;
        DB::select('select set_config(?, ?, false)', ['app.user_id', $userId]);
    }

    public function setTenant(string $tenantId): void
    {
        $this->assertUuid($tenantId);
        $this->assertRuntimeRole();
        $this->tenantId = $tenantId;
        DB::select('select set_config(?, ?, false)', ['app.tenant_id', $tenantId]);
    }

    public function setMembership(Membership $membership): void
    {
        $this->membership = $membership;
    }

    public function reset(): void
    {
        $this->tenantId = null;
        $this->userId = null;
        $this->membership = null;

        $pdo = DB::connection()->getPdo();

        if ($pdo === null) {
            return;
        }

        DB::select("select set_config('app.tenant_id', '', false)");
        DB::select("select set_config('app.user_id', '', false)");
    }

    public function assertRuntimeRole(): void
    {
        if (self::$runtimeRoleChecked) {
            return;
        }

        $row = DB::selectOne(
            'select rolsuper, rolbypassrls from pg_roles where rolname = current_user',
        );

        if ($row === null || (bool) $row->rolsuper || (bool) $row->rolbypassrls) {
            throw new RuntimeException('The database runtime role can bypass row-level security.');
        }

        self::$runtimeRoleChecked = true;
    }

    private function assertUuid(string $value): void
    {
        if (! Str::isUuid($value)) {
            throw new RuntimeException('Tenant context identifiers must be UUIDs.');
        }
    }
}
