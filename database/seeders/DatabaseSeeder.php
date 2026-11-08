<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\User;
use App\Domain\Organisation\Models\Ward;
use App\Domain\Rosters\Models\DutyAssignment;
use App\Domain\Rosters\Models\Roster;
use App\Domain\Shifts\Models\ShiftDefinition;
use App\Domain\Shifts\Support\ShiftWindow;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Membership;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Synthetic hospitals only. These are not real facilities or staff.
 */
class DatabaseSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'local-demo-only-password';

    public function run(): void
    {
        $this->hospital(
            name: 'Demo Hospital North',
            timezone: 'Asia/Kolkata',
            adminEmail: 'admin.north@example.com',
            supervisorEmail: 'supervisor.north@example.com',
            staffEmail: 'staff.north@example.com',
            wardCode: 'GEN',
            wardName: 'General Ward',
            nurses: 4,
        );

        $this->hospital(
            name: 'Demo Hospital South',
            timezone: 'Asia/Kolkata',
            adminEmail: 'admin.south@example.com',
            supervisorEmail: 'supervisor.south@example.com',
            staffEmail: 'staff.south@example.com',
            wardCode: 'MED',
            wardName: 'Medical Ward',
            nurses: 2,
        );
    }

    private function hospital(
        string $name,
        string $timezone,
        string $adminEmail,
        string $supervisorEmail,
        string $staffEmail,
        string $wardCode,
        string $wardName,
        int $nurses,
    ): void {
        $context = app(TenantContext::class);
        $tenantId = (string) Str::uuid7();
        $context->setTenant($tenantId);

        $tenant = new Tenant;
        $tenant->id = $tenantId;
        $tenant->forceFill([
            'name' => $name,
            'timezone' => $timezone,
            'status' => 'active',
            'settings' => [],
        ])->save();

        $admin = $this->user('Admin Demo', $adminEmail);
        $supervisor = $this->user('Supervisor Demo', $supervisorEmail);
        $staff = $this->user('Staff Demo', $staffEmail);

        $context->setUser((string) $admin->id);
        $this->membership($tenantId, (string) $admin->id, 'hospital_admin');
        $this->membership($tenantId, (string) $supervisor->id, 'supervisor');
        $this->membership($tenantId, (string) $staff->id, 'staff');

        $ward = new Ward;
        $ward->forceFill([
            'tenant_id' => $tenantId,
            'code' => $wardCode,
            'name' => $wardName,
            'status' => 'active',
        ])->save();

        $morning = $this->shift($tenantId, 'MORN', 'Morning', '07:00', '15:00');
        $this->shift($tenantId, 'EVE', 'Evening', '15:00', '23:00');
        $night = $this->shift($tenantId, 'NIGHT', 'Night', '20:00', '08:00');

        $employees = [];

        for ($index = 1; $index <= $nurses; $index++) {
            $employee = new Employee;
            $employee->forceFill([
                'tenant_id' => $tenantId,
                'employee_code' => sprintf('N-%02d', $index),
                'full_name' => sprintf('Nurse Demo %02d', $index),
                'status' => 'active',
            ])->save();
            $employees[] = $employee;
        }

        $inactive = new Employee;
        $inactive->forceFill([
            'tenant_id' => $tenantId,
            'employee_code' => 'N-OFF',
            'full_name' => 'Nurse Demo Inactive',
            'status' => 'inactive',
            'inactive_from' => now()->toDateString(),
        ])->save();

        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd = now()->endOfMonth()->toDateString();

        $roster = new Roster;
        $roster->forceFill([
            'tenant_id' => $tenantId,
            'ward_id' => $ward->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'status' => 'draft',
            'lock_version' => 2,
            'created_by' => $admin->id,
        ])->save();

        $this->assignment($tenant, $roster, $employees[0], $morning, $periodStart);
        $this->assignment($tenant, $roster, $employees[1] ?? $employees[0], $night, $periodStart);

        $context->reset();
    }

    private function user(string $name, string $email): User
    {
        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => self::DEMO_PASSWORD,
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    private function membership(string $tenantId, string $userId, string $role): void
    {
        $membership = new Membership;
        $membership->forceFill([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'role' => $role,
            'status' => 'active',
        ])->save();
    }

    private function shift(string $tenantId, string $code, string $name, string $start, string $end): ShiftDefinition
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

    private function assignment(Tenant $tenant, Roster $roster, Employee $employee, ShiftDefinition $shift, string $dutyDate): void
    {
        $window = ShiftWindow::forDutyDate($dutyDate, $shift->startTime(), $shift->endTime(), $tenant->timezone);

        $assignment = new DutyAssignment;
        $assignment->forceFill([
            'tenant_id' => $tenant->id,
            'roster_id' => $roster->id,
            'employee_id' => $employee->id,
            'shift_definition_id' => $shift->id,
            'duty_date' => $dutyDate,
            'starts_at' => $window->startsAt,
            'ends_at' => $window->endsAt,
            'status' => 'scheduled',
        ])->save();
    }
}
