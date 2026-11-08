<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Shared\Queue\ApplyTenantContext;
use App\Domain\Shared\Queue\ClaimIdempotency;
use App\Domain\Shared\Queue\IdempotencyStore;
use App\Domain\Shared\Queue\IdempotentWork;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\MigratesWithOwner;
use Tests\Support\HospitalFixture;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use MigratesWithOwner;

    public function test_cross_tenant_records_are_not_found_and_requests_do_not_leak(): void
    {
        $north = HospitalFixture::create('north');
        $south = HospitalFixture::create('south');

        $this->asMember($north->admin, $north->adminMembershipId)
            ->patchJson('/api/v1/staff/'.$south->nurse->id, [
                'status' => 'inactive',
            ])->assertNotFound();

        $roster = $this->asMember($south->supervisor, $south->supervisorMembershipId)
            ->postJson('/api/v1/rosters', [
                'ward_id' => $south->ward->id,
                'period_start' => '2026-11-01',
                'period_end' => '2026-11-30',
            ])
            ->assertCreated()
            ->json('data.id');

        $this->asMember($north->admin, $north->adminMembershipId)
            ->getJson('/api/v1/rosters/'.$roster)
            ->assertNotFound();

        $this->asMember($north->supervisor, $north->supervisorMembershipId)
            ->postJson('/api/v1/rosters/'.$roster.'/assignments', [
                'employee_id' => $north->nurse->id,
                'shift_definition_id' => $north->morning->id,
                'duty_date' => '2026-11-02',
                'lock_version' => 0,
            ])->assertNotFound();

        $northNames = $this->asMember($north->admin, $north->adminMembershipId)
            ->getJson('/api/v1/staff')
            ->assertOk()
            ->json('data');

        $southNames = $this->asMember($south->admin, $south->adminMembershipId)
            ->getJson('/api/v1/staff')
            ->assertOk()
            ->json('data');

        $this->assertSame(['Nurse Demo 01', 'Nurse Demo 02', 'Nurse Demo Inactive'], array_column($northNames, 'full_name'));
        $this->assertSame(['Nurse Demo 01', 'Nurse Demo 02', 'Nurse Demo Inactive'], array_column($southNames, 'full_name'));
        $this->assertNotContains($south->nurse->id, array_column($northNames, 'id'));
        $this->assertNotContains($north->nurse->id, array_column($southNames, 'id'));
    }

    public function test_runtime_role_cannot_bypass_rls_and_missing_tenant_fails_closed(): void
    {
        $north = HospitalFixture::create('north');
        $south = HospitalFixture::create('south');

        $role = DB::selectOne('select current_user as role, rolsuper, rolbypassrls from pg_roles where rolname = current_user');
        $this->assertSame('nurseroster_app', $role->role);
        $this->assertFalse((bool) $role->rolsuper);
        $this->assertFalse((bool) $role->rolbypassrls);

        $owner = DB::selectOne("select rolsuper, rolbypassrls from pg_roles where rolname = 'nurseroster_owner'");
        $this->assertFalse((bool) $owner->rolsuper);
        $this->assertFalse((bool) $owner->rolbypassrls);

        $ownerName = DB::selectOne("select tableowner from pg_tables where tablename = 'employees'");
        $this->assertSame('nurseroster_owner', $ownerName->tableowner);
        $this->assertNotSame('nurseroster_app', $ownerName->tableowner);

        DB::statement('set row_security = off');
        $bypass = $this->expectQueryFailure(function (): void {
            DB::table('employees')->count();
        });
        $this->assertStringContainsString('row-level security', $bypass->getMessage());
        DB::statement('set row_security = on');

        app(TenantContext::class)->reset();

        $missing = $this->expectQueryFailure(function (): void {
            DB::table('employees')->count();
        });
        $this->assertNotSame('', $missing->getMessage());

        $north->adopt();
        $visible = Employee::withoutGlobalScopes()->pluck('id')->all();
        $this->assertContains($north->nurse->id, $visible);
        $this->assertNotContains($south->nurse->id, $visible);
    }

    public function test_spoofed_membership_is_rejected(): void
    {
        $north = HospitalFixture::create('north');
        $south = HospitalFixture::create('south');

        $this->asMember($north->admin, $south->adminMembershipId)
            ->getJson('/api/v1/me')
            ->assertForbidden();
    }

    public function test_job_tenant_context_resets_after_failure_and_idempotency_is_once(): void
    {
        $north = HospitalFixture::create('north');
        $seen = null;
        $job = new class((string) $north->tenant->id)
        {
            public function __construct(public string $tenantId) {}
        };

        try {
            (new ApplyTenantContext)->handle($job, function () use (&$seen): void {
                $seen = DB::selectOne("select current_setting('app.tenant_id') as tenant_id")->tenant_id;
                throw new RuntimeException('job failed');
            });
            $this->fail('The job should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('job failed', $exception->getMessage());
        }

        $this->assertSame((string) $north->tenant->id, $seen);
        $this->assertNull(app(TenantContext::class)->tenantId());
        $this->assertSame('', DB::selectOne("select current_setting('app.tenant_id', true) as tenant_id")->tenant_id);

        $north->adopt();
        $store = app(IdempotencyStore::class);
        $this->assertTrue($store->claim((string) $north->tenant->id, 'exports', 'version-1'));
        $this->assertFalse($store->claim((string) $north->tenant->id, 'exports', 'version-1'));

        $runs = 0;
        $idempotent = new class((string) $north->tenant->id) implements IdempotentWork
        {
            public function __construct(public string $tenantId) {}

            public function idempotencyScope(): string
            {
                return 'exports';
            }

            public function idempotencyKey(): string
            {
                return 'version-2';
            }
        };

        $middleware = new ClaimIdempotency;
        $middleware->handle($idempotent, function () use (&$runs): void {
            $runs++;
        });
        $middleware->handle($idempotent, function () use (&$runs): void {
            $runs++;
        });

        $this->assertSame(1, $runs);
        $this->assertNotEmpty(Str::uuid7());
    }
}
