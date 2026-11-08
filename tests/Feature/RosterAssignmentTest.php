<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Shared\Support\PageSize;
use App\Domain\Staff\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\MigratesWithOwner;
use Tests\Support\HospitalFixture;
use Tests\TestCase;

class RosterAssignmentTest extends TestCase
{
    use MigratesWithOwner;

    public function test_roles_and_roster_persistence(): void
    {
        $hospital = HospitalFixture::create('north');

        $this->asMember($hospital->staffUser, $hospital->staffMembershipId)
            ->postJson('/api/v1/staff', [
                'employee_code' => 'N-99',
                'full_name' => 'Nurse Demo 99',
            ])->assertForbidden();

        $this->asMember($hospital->supervisor, $hospital->supervisorMembershipId)
            ->postJson('/api/v1/staff', [
                'employee_code' => 'N-99',
                'full_name' => 'Nurse Demo 99',
            ])->assertForbidden();

        $this->asMember($hospital->admin, $hospital->adminMembershipId)
            ->postJson('/api/v1/staff', [
                'employee_code' => 'N-99',
                'full_name' => 'Nurse Demo 99',
            ])->assertCreated()
            ->assertJsonPath('data.full_name', 'Nurse Demo 99');

        $this->asMember($hospital->staffUser, $hospital->staffMembershipId)
            ->postJson('/api/v1/rosters', [
                'ward_id' => $hospital->ward->id,
                'period_start' => '2026-11-01',
                'period_end' => '2026-11-30',
            ])->assertForbidden();

        $created = $this->asMember($hospital->supervisor, $hospital->supervisorMembershipId)
            ->postJson('/api/v1/rosters', [
                'ward_id' => $hospital->ward->id,
                'period_start' => '2026-11-01',
                'period_end' => '2026-11-30',
            ])->assertCreated();

        $rosterId = $created->json('data.id');

        $this->asMember($hospital->staffUser, $hospital->staffMembershipId)
            ->postJson('/api/v1/rosters/'.$rosterId.'/assignments', [
                'employee_id' => $hospital->nurse->id,
                'shift_definition_id' => $hospital->morning->id,
                'duty_date' => '2026-11-02',
                'lock_version' => 0,
            ])->assertForbidden();

        $this->asMember($hospital->supervisor, $hospital->supervisorMembershipId)
            ->postJson('/api/v1/rosters/'.$rosterId.'/assignments', [
                'employee_id' => $hospital->nurse->id,
                'shift_definition_id' => $hospital->morning->id,
                'duty_date' => '2026-11-02',
                'lock_version' => 0,
            ])->assertCreated();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $grid = $this->asMember($hospital->staffUser, $hospital->staffMembershipId)
            ->getJson('/api/v1/rosters/'.$rosterId)
            ->assertOk()
            ->assertJsonPath('data.assignments.0.duty_date', '2026-11-02')
            ->assertJsonPath('data.assignments.0.employee_id', $hospital->nurse->id)
            ->assertJsonPath('data.timezone', 'Asia/Kolkata');

        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql): bool => ! str_contains($sql, 'set_config'))->count();
        $this->assertLessThanOrEqual(12, $queries);
        $this->assertCount(1, $grid->json('data.assignments'));

        $this->asMember($hospital->admin, $hospital->adminMembershipId)
            ->getJson('/api/v1/wards?per_page=500')
            ->assertOk()
            ->assertJsonPath('meta.per_page', PageSize::MAX);
    }

    public function test_inactive_staff_and_overlaps_including_overnight_are_rejected(): void
    {
        $hospital = HospitalFixture::create('north');

        $rosterId = $this->asMember($hospital->admin, $hospital->adminMembershipId)
            ->postJson('/api/v1/rosters', [
                'ward_id' => $hospital->ward->id,
                'period_start' => '2026-10-01',
                'period_end' => '2026-11-30',
            ])->assertCreated()->json('data.id');

        $this->asMember($hospital->admin, $hospital->adminMembershipId)
            ->postJson('/api/v1/rosters/'.$rosterId.'/assignments', [
                'employee_id' => $hospital->inactiveNurse->id,
                'shift_definition_id' => $hospital->morning->id,
                'duty_date' => '2026-10-15',
                'lock_version' => 0,
            ])->assertStatus(422)
            ->assertJsonPath('code', 'STAFF_INACTIVE');

        $hospital->adopt();
        $leaving = new Employee;
        $leaving->forceFill([
            'tenant_id' => $hospital->tenant->id,
            'employee_code' => 'N-LEAVE',
            'full_name' => 'Nurse Demo Leaving',
            'status' => 'active',
            'inactive_from' => '2026-10-31',
        ])->save();

        $this->asMember($hospital->admin, $hospital->adminMembershipId)
            ->postJson('/api/v1/rosters/'.$rosterId.'/assignments', [
                'employee_id' => $leaving->id,
                'shift_definition_id' => $hospital->morning->id,
                'duty_date' => '2026-10-30',
                'lock_version' => 0,
            ])->assertCreated();

        $this->asMember($hospital->admin, $hospital->adminMembershipId)
            ->postJson('/api/v1/rosters/'.$rosterId.'/assignments', [
                'employee_id' => $leaving->id,
                'shift_definition_id' => $hospital->morning->id,
                'duty_date' => '2026-10-31',
                'lock_version' => 1,
            ])->assertStatus(422)
            ->assertJsonPath('code', 'STAFF_INACTIVE');

        $night = $this->asMember($hospital->admin, $hospital->adminMembershipId)
            ->postJson('/api/v1/rosters/'.$rosterId.'/assignments', [
                'employee_id' => $hospital->nurse->id,
                'shift_definition_id' => $hospital->night->id,
                'duty_date' => '2026-10-31',
                'lock_version' => 1,
            ])->assertCreated();

        $this->assertStringContainsString('2026-10-31T14:30:00', (string) $night->json('data.starts_at'));
        $this->assertStringContainsString('2026-11-01T02:30:00', (string) $night->json('data.ends_at'));

        $this->asMember($hospital->admin, $hospital->adminMembershipId)
            ->postJson('/api/v1/rosters/'.$rosterId.'/assignments', [
                'employee_id' => $hospital->nurse->id,
                'shift_definition_id' => $hospital->morning->id,
                'duty_date' => '2026-11-01',
                'lock_version' => 2,
            ])->assertStatus(409)
            ->assertJsonPath('code', 'ASSIGNMENT_OVERLAP');

        $this->asMember($hospital->admin, $hospital->adminMembershipId)
            ->postJson('/api/v1/rosters/'.$rosterId.'/assignments', [
                'employee_id' => $hospital->otherNurse->id,
                'shift_definition_id' => $hospital->morning->id,
                'duty_date' => '2026-11-01',
                'lock_version' => 2,
            ])->assertCreated();

        $this->asMember($hospital->admin, $hospital->adminMembershipId)
            ->postJson('/api/v1/rosters/'.$rosterId.'/assignments', [
                'employee_id' => $hospital->otherNurse->id,
                'shift_definition_id' => $hospital->evening->id,
                'duty_date' => '2026-11-02',
                'lock_version' => 3,
            ])->assertCreated();

        $late = $this->asMember($hospital->admin, $hospital->adminMembershipId)
            ->postJson('/api/v1/shift-definitions', [
                'code' => 'LATE',
                'name' => 'Late',
                'start_time' => '23:00',
                'end_time' => '07:00',
                'break_minutes' => 0,
            ])->assertCreated();

        $this->asMember($hospital->admin, $hospital->adminMembershipId)
            ->postJson('/api/v1/rosters/'.$rosterId.'/assignments', [
                'employee_id' => $hospital->otherNurse->id,
                'shift_definition_id' => $late->json('data.id'),
                'duty_date' => '2026-11-02',
                'lock_version' => 4,
            ])->assertCreated();

        $hospital->adopt();

        $trigger = $this->expectQueryFailure(function () use ($hospital, $rosterId): void {
            DB::table('duty_assignments')->insert([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $hospital->tenant->id,
                'roster_id' => $rosterId,
                'employee_id' => $hospital->inactiveNurse->id,
                'shift_definition_id' => $hospital->morning->id,
                'duty_date' => '2026-10-20',
                'starts_at' => '2026-10-20 01:30:00+00',
                'ends_at' => '2026-10-20 09:30:00+00',
                'status' => 'scheduled',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
        $this->assertStringContainsString('STAFF_INACTIVE', $trigger->getMessage());
    }

    public function test_configurable_rules_cannot_replace_integrity_and_snapshots_are_append_only(): void
    {
        $hospital = HospitalFixture::create('north');
        $hospital->adopt();

        $ruleSetId = (string) Str::uuid7();
        DB::table('rule_sets')->insert([
            'id' => $ruleSetId,
            'tenant_id' => $hospital->tenant->id,
            'version' => 1,
            'status' => 'draft',
            'effective_from' => null,
            'created_by' => $hospital->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $blocked = $this->expectQueryFailure(function () use ($hospital, $ruleSetId): void {
            DB::table('rule_set_rules')->insert([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $hospital->tenant->id,
                'rule_set_id' => $ruleSetId,
                'rule_type' => 'BR-01',
                'params' => '{}',
                'severity' => 'warning',
                'source_kind' => 'hospital_policy',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
        $this->assertStringContainsString('23514', $blocked->getMessage());

        DB::table('rule_set_rules')->insert([
            'id' => (string) Str::uuid7(),
            'tenant_id' => $hospital->tenant->id,
            'rule_set_id' => $ruleSetId,
            'rule_type' => 'BR-05',
            'params' => '{"min_staff":2}',
            'severity' => 'warning',
            'source_kind' => 'hospital_policy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $versionId = (string) Str::uuid7();
        DB::table('roster_versions')->insert([
            'id' => $versionId,
            'tenant_id' => $hospital->tenant->id,
            'roster_id' => $this->rosterId($hospital),
            'version_no' => 1,
            'snapshot' => '{"assignments":[]}',
            'snapshot_sha256' => hash('sha256', '{"assignments":[]}'),
            'published_by' => $hospital->admin->id,
            'published_at' => now(),
            'created_at' => now(),
        ]);

        $update = $this->expectQueryFailure(function () use ($versionId): void {
            DB::table('roster_versions')->where('id', $versionId)->update(['version_no' => 2]);
        });
        $this->assertStringContainsString('42501', $update->getMessage());
    }

    private function rosterId(HospitalFixture $hospital): string
    {
        $rosterId = (string) Str::uuid7();
        DB::table('rosters')->insert([
            'id' => $rosterId,
            'tenant_id' => $hospital->tenant->id,
            'ward_id' => $hospital->ward->id,
            'period_start' => '2026-12-01',
            'period_end' => '2026-12-31',
            'status' => 'draft',
            'lock_version' => 0,
            'created_by' => $hospital->admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $rosterId;
    }
}
