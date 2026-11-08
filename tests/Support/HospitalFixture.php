<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Organisation\Models\Ward;
use App\Domain\Shifts\Models\ShiftDefinition;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Membership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Str;

final class HospitalFixture
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly User $admin,
        public readonly string $adminMembershipId,
        public readonly User $supervisor,
        public readonly string $supervisorMembershipId,
        public readonly User $staffUser,
        public readonly string $staffMembershipId,
        public readonly Ward $ward,
        public readonly ShiftDefinition $morning,
        public readonly ShiftDefinition $evening,
        public readonly ShiftDefinition $night,
        public readonly Employee $nurse,
        public readonly Employee $otherNurse,
        public readonly Employee $inactiveNurse,
    ) {}

    public static function create(string $prefix): self
    {
        $context = app(TenantContext::class);
        $tenantId = (string) Str::uuid7();
        $context->setTenant($tenantId);

        $tenant = new Tenant;
        $tenant->id = $tenantId;
        $tenant->forceFill([
            'name' => 'Demo Hospital '.$prefix,
            'timezone' => 'Asia/Kolkata',
            'status' => 'active',
            'settings' => [],
        ])->save();

        $admin = self::user($prefix.' Admin Demo', $prefix.'.admin@example.com');
        $supervisor = self::user($prefix.' Supervisor Demo', $prefix.'.supervisor@example.com');
        $staff = self::user($prefix.' Staff Demo', $prefix.'.staff@example.com');

        $context->setUser((string) $admin->id);

        return new self(
            tenant: $tenant,
            admin: $admin,
            adminMembershipId: self::membership($tenantId, (string) $admin->id, 'hospital_admin'),
            supervisor: $supervisor,
            supervisorMembershipId: self::membership($tenantId, (string) $supervisor->id, 'supervisor'),
            staffUser: $staff,
            staffMembershipId: self::membership($tenantId, (string) $staff->id, 'staff'),
            ward: self::ward($tenantId, 'GEN', 'General Ward'),
            morning: self::shift($tenantId, 'MORN', 'Morning', '07:00:00', '15:00:00'),
            evening: self::shift($tenantId, 'EVE', 'Evening', '15:00:00', '23:00:00'),
            night: self::shift($tenantId, 'NIGHT', 'Night', '20:00:00', '08:00:00'),
            nurse: self::employee($tenantId, 'N-01', 'Nurse Demo 01', 'active', null),
            otherNurse: self::employee($tenantId, 'N-02', 'Nurse Demo 02', 'active', null),
            inactiveNurse: self::employee($tenantId, 'N-OFF', 'Nurse Demo Inactive', 'inactive', '2026-01-01'),
        );
    }

    public function adopt(string $role = 'admin'): void
    {
        $context = app(TenantContext::class);
        $user = match ($role) {
            'supervisor' => $this->supervisor,
            'staff' => $this->staffUser,
            default => $this->admin,
        };
        $context->setUser((string) $user->id);
        $context->setTenant((string) $this->tenant->id);
    }

    private static function user(string $name, string $email): User
    {
        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => 'local-demo-only-password',
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    private static function membership(string $tenantId, string $userId, string $role): string
    {
        $membership = new Membership;
        $membership->forceFill([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'role' => $role,
            'status' => 'active',
        ])->save();

        return (string) $membership->id;
    }

    private static function ward(string $tenantId, string $code, string $name): Ward
    {
        $ward = new Ward;
        $ward->forceFill([
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $name,
            'status' => 'active',
        ])->save();

        return $ward;
    }

    private static function shift(string $tenantId, string $code, string $name, string $start, string $end): ShiftDefinition
    {
        $shift = new ShiftDefinition;
        $shift->forceFill([
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => $name,
            'start_time' => $start,
            'end_time' => $end,
            'break_minutes' => 30,
            'kind' => 'duty',
            'status' => 'active',
        ])->save();

        return $shift;
    }

    private static function employee(
        string $tenantId,
        string $code,
        string $name,
        string $status,
        ?string $inactiveFrom,
    ): Employee {
        $employee = new Employee;
        $employee->forceFill([
            'tenant_id' => $tenantId,
            'employee_code' => $code,
            'full_name' => $name,
            'status' => $status,
            'inactive_from' => $inactiveFrom,
        ])->save();

        return $employee;
    }
}
