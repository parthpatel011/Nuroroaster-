<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Shared\Support\PageSize;
use App\Domain\Shifts\Actions\CreateShiftDefinition;
use App\Domain\Shifts\Models\ShiftDefinition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreShiftDefinitionRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftDefinitionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ShiftDefinition::class);

        $page = ShiftDefinition::query()
            ->orderBy('start_time')
            ->orderBy('code')
            ->paginate(PageSize::from($request));

        return response()->json([
            'data' => $page->getCollection()->map(fn (ShiftDefinition $shift): array => $this->payload($shift))->values(),
            'meta' => $this->meta($page),
        ]);
    }

    public function store(StoreShiftDefinitionRequest $request, CreateShiftDefinition $action): JsonResponse
    {
        $this->authorize('create', ShiftDefinition::class);

        $shift = $action(
            $request->string('code')->toString(),
            $request->string('name')->toString(),
            $request->string('start_time')->toString(),
            $request->string('end_time')->toString(),
            $request->integer('break_minutes'),
        );

        return response()->json(['data' => $this->payload($shift)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ShiftDefinition $shift): array
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
