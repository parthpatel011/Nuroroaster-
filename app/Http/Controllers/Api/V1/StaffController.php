<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\Support\PageSize;
use App\Domain\Staff\Actions\CreateEmployee;
use App\Domain\Staff\Actions\UpdateEmployee;
use App\Domain\Staff\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEmployeeRequest;
use App\Http\Requests\Api\V1\UpdateEmployeeRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $page = Employee::query()
            ->orderBy('full_name')
            ->orderBy('employee_code')
            ->paginate(PageSize::from($request));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Employee $employee): array => $this->payload($employee))->values(),
            'meta' => $this->meta($page),
        ]);
    }

    public function store(StoreEmployeeRequest $request, CreateEmployee $action): JsonResponse
    {
        $this->authorize('create', Employee::class);

        $employee = $action(
            $request->string('employee_code')->toString(),
            $request->string('full_name')->toString(),
        );

        return response()->json(['data' => $this->payload($employee)], 201);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee, UpdateEmployee $action): JsonResponse
    {
        $this->authorize('update', $employee);

        $inactiveFrom = $request->input('inactive_from');
        $employee = $action(
            $employee,
            $request->string('status')->toString(),
            is_string($inactiveFrom) ? $inactiveFrom : null,
        );

        return response()->json(['data' => $this->payload($employee)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'full_name' => $employee->full_name,
            'status' => $employee->status,
            'inactive_from' => $employee->inactive_from?->toDateString(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function meta(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ];
    }
}
