<?php

declare(strict_types=1);

namespace App\Domain\Rosters\Actions;

use App\Domain\Rosters\Exceptions\RosterDomainException;
use App\Domain\Rosters\Models\DutyAssignment;
use App\Domain\Rosters\Models\Roster;
use App\Domain\Shared\Support\ErrorCode;
use App\Domain\Shared\Support\PostgresExceptionMapper;
use App\Domain\Shifts\Models\ShiftDefinition;
use App\Domain\Shifts\Support\ShiftWindow;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AddAssignment
{
    public function __construct(private readonly TenantContext $context) {}

    public function __invoke(
        Roster $roster,
        string $employeeId,
        string $shiftDefinitionId,
        string $dutyDate,
        int $lockVersion,
    ): DutyAssignment {
        try {
            return DB::transaction(function () use ($roster, $employeeId, $shiftDefinitionId, $dutyDate, $lockVersion): DutyAssignment {
                $this->claimLock($roster, $lockVersion);

                if ($roster->status !== 'draft') {
                    throw new RosterDomainException(ErrorCode::ROSTER_NOT_DRAFT, 'Only a draft roster can be edited.', 409);
                }

                if ($dutyDate < $roster->period_start->toDateString() || $dutyDate > $roster->period_end->toDateString()) {
                    throw new RosterDomainException(ErrorCode::DUTY_OUTSIDE_PERIOD, 'The duty date is outside the roster period.', 422);
                }

                $employee = Employee::query()->whereKey($employeeId)->first();

                if ($employee === null) {
                    throw new RosterDomainException(ErrorCode::ASSIGNMENT_TARGET_NOT_FOUND, 'The staff member was not found.', 404);
                }

                if ($employee->isBlockedOn($dutyDate)) {
                    throw new RosterDomainException(ErrorCode::STAFF_INACTIVE, 'Inactive staff cannot be assigned.', 422);
                }

                $shift = ShiftDefinition::query()->whereKey($shiftDefinitionId)->first();

                if ($shift === null) {
                    throw new RosterDomainException(ErrorCode::ASSIGNMENT_TARGET_NOT_FOUND, 'The shift definition was not found.', 404);
                }

                if ($shift->status !== 'active') {
                    throw new RosterDomainException(ErrorCode::SHIFT_INACTIVE, 'Inactive shift definitions cannot be assigned.', 422);
                }

                $tenant = Tenant::query()->findOrFail($this->context->tenantId());

                try {
                    $window = ShiftWindow::forDutyDate(
                        $dutyDate,
                        $shift->startTime(),
                        $shift->endTime(),
                        $tenant->timezone,
                    );
                } catch (InvalidArgumentException $exception) {
                    throw new RosterDomainException(ErrorCode::DUTY_DATE_MISMATCH, $exception->getMessage(), 422);
                }

                return DutyAssignment::query()->create([
                    'roster_id' => $roster->id,
                    'employee_id' => $employee->id,
                    'shift_definition_id' => $shift->id,
                    'duty_date' => $dutyDate,
                    'starts_at' => $window->startsAt,
                    'ends_at' => $window->endsAt,
                    'status' => 'scheduled',
                ]);
            });
        } catch (QueryException $exception) {
            $mapped = PostgresExceptionMapper::map($exception);

            if ($mapped !== null) {
                throw $mapped;
            }

            throw $exception;
        }
    }

    private function claimLock(Roster $roster, int $lockVersion): void
    {
        $updated = Roster::query()
            ->whereKey($roster->id)
            ->where('lock_version', $lockVersion)
            ->update([
                'lock_version' => $lockVersion + 1,
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new RosterDomainException(
                ErrorCode::STALE_VERSION,
                'The roster changed. Reload it and try again.',
                409,
            );
        }
    }
}
