<?php

declare(strict_types=1);

namespace App\Domain\Rosters\Queries;

use App\Domain\Rosters\Models\Roster;
use App\Domain\Shifts\Models\ShiftDefinition;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Carbon\CarbonPeriod;

final class RosterGridQuery
{
    public const STAFF_LIMIT = 100;

    public function __construct(private readonly TenantContext $context) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Roster $roster): array
    {
        $roster->load([
            'ward',
            'assignments' => function ($query): void {
                $query->where('status', 'scheduled')->orderBy('duty_date')->orderBy('starts_at');
            },
            'assignments.shiftDefinition',
        ]);

        $assignedEmployeeIds = $roster->assignments->pluck('employee_id')->all();
        $assignedShiftIds = $roster->assignments->pluck('shift_definition_id')->all();

        $staff = Employee::query()
            ->where(function ($query) use ($assignedEmployeeIds): void {
                $query->where('status', 'active');

                if ($assignedEmployeeIds !== []) {
                    $query->orWhereIn('id', $assignedEmployeeIds);
                }
            })
            ->orderBy('full_name')
            ->orderBy('employee_code')
            ->limit(self::STAFF_LIMIT)
            ->get();

        $shifts = ShiftDefinition::query()
            ->where(function ($query) use ($assignedShiftIds): void {
                $query->where('status', 'active');

                if ($assignedShiftIds !== []) {
                    $query->orWhereIn('id', $assignedShiftIds);
                }
            })
            ->orderBy('start_time')
            ->orderBy('code')
            ->limit(self::STAFF_LIMIT)
            ->get();

        $tenant = Tenant::query()->findOrFail($this->context->tenantId());
        $days = [];

        foreach (CarbonPeriod::create($roster->period_start, $roster->period_end) as $day) {
            $days[] = $day->toDateString();
        }

        return [
            'id' => $roster->id,
            'status' => $roster->status,
            'lock_version' => $roster->lock_version,
            'period_start' => $roster->period_start->toDateString(),
            'period_end' => $roster->period_end->toDateString(),
            'timezone' => $tenant->timezone,
            'ward' => [
                'id' => $roster->ward->id,
                'code' => $roster->ward->code,
                'name' => $roster->ward->name,
            ],
            'days' => $days,
            'staff_truncated' => $staff->count() === self::STAFF_LIMIT,
            'staff' => $staff->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'status' => $employee->status,
                'inactive_from' => $employee->inactive_from?->toDateString(),
            ])->all(),
            'shift_definitions' => $shifts->map(fn (ShiftDefinition $shift): array => $this->shiftPayload($shift))->all(),
            'assignments' => $roster->assignments->map(function ($assignment): array {
                $shift = $assignment->shiftDefinition;

                return [
                    'id' => $assignment->id,
                    'employee_id' => $assignment->employee_id,
                    'shift_definition_id' => $assignment->shift_definition_id,
                    'duty_date' => $assignment->duty_date->toDateString(),
                    'starts_at' => $assignment->starts_at->toIso8601String(),
                    'ends_at' => $assignment->ends_at->toIso8601String(),
                    'ends_next_day' => $shift->endTime() <= $shift->startTime(),
                    'shift_code' => $shift->code,
                    'shift_name' => $shift->name,
                    'start_time' => substr($shift->startTime(), 0, 5),
                    'end_time' => substr($shift->endTime(), 0, 5),
                ];
            })->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function shiftPayload(ShiftDefinition $shift): array
    {
        return [
            'id' => $shift->id,
            'code' => $shift->code,
            'name' => $shift->name,
            'start_time' => substr($shift->startTime(), 0, 5),
            'end_time' => substr($shift->endTime(), 0, 5),
            'ends_next_day' => $shift->endTime() <= $shift->startTime(),
            'break_minutes' => $shift->break_minutes,
            'status' => $shift->status,
        ];
    }
}
