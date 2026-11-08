<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Rosters\Actions\AddAssignment;
use App\Domain\Rosters\Actions\RemoveAssignment;
use App\Domain\Rosters\Models\DutyAssignment;
use App\Domain\Rosters\Models\Roster;
use App\Domain\Shared\Support\ErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Problem\ProblemResponse;
use App\Http\Requests\Api\V1\DeleteAssignmentRequest;
use App\Http\Requests\Api\V1\StoreAssignmentRequest;
use Illuminate\Http\JsonResponse;

class AssignmentController extends Controller
{
    public function store(StoreAssignmentRequest $request, Roster $roster, AddAssignment $action): JsonResponse
    {
        $this->authorize('assign', $roster);

        $assignment = $action(
            $roster,
            $request->string('employee_id')->toString(),
            $request->string('shift_definition_id')->toString(),
            $request->string('duty_date')->toString(),
            $request->integer('lock_version'),
        );

        return response()->json([
            'data' => [
                'id' => $assignment->id,
                'roster_id' => $assignment->roster_id,
                'employee_id' => $assignment->employee_id,
                'shift_definition_id' => $assignment->shift_definition_id,
                'duty_date' => $assignment->duty_date->toDateString(),
                'starts_at' => $assignment->starts_at->toIso8601String(),
                'ends_at' => $assignment->ends_at->toIso8601String(),
                'lock_version' => $request->integer('lock_version') + 1,
            ],
        ], 201);
    }

    public function destroy(
        DeleteAssignmentRequest $request,
        Roster $roster,
        string $assignment,
        RemoveAssignment $action,
    ): JsonResponse {
        $this->authorize('assign', $roster);

        $model = DutyAssignment::query()
            ->whereKey($assignment)
            ->where('roster_id', $roster->id)
            ->first();

        if ($model === null) {
            return ProblemResponse::make(
                404,
                ErrorCode::ASSIGNMENT_TARGET_NOT_FOUND,
                'Assignment not found',
                'The assignment was not found.',
            );
        }

        $action($roster, $model, $request->integer('lock_version'));

        return response()->json([
            'data' => [
                'cancelled' => true,
                'lock_version' => $request->integer('lock_version') + 1,
            ],
        ]);
    }
}
