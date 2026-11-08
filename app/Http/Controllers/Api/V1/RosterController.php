<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Models\User;
use App\Domain\Organisation\Models\Ward;
use App\Domain\Rosters\Actions\CreateRoster;
use App\Domain\Rosters\Models\Roster;
use App\Domain\Rosters\Queries\RosterGridQuery;
use App\Domain\Shared\Support\ErrorCode;
use App\Domain\Shared\Support\PageSize;
use App\Http\Controllers\Controller;
use App\Http\Problem\ProblemResponse;
use App\Http\Requests\Api\V1\StoreRosterRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RosterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Roster::class);

        $page = Roster::query()
            ->with('ward')
            ->orderByDesc('period_start')
            ->orderBy('id')
            ->paginate(PageSize::from($request));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Roster $roster): array => [
                'id' => $roster->id,
                'status' => $roster->status,
                'lock_version' => $roster->lock_version,
                'period_start' => $roster->period_start->toDateString(),
                'period_end' => $roster->period_end->toDateString(),
                'ward' => [
                    'id' => $roster->ward->id,
                    'code' => $roster->ward->code,
                    'name' => $roster->ward->name,
                ],
            ])->values(),
            'meta' => $this->meta($page),
        ]);
    }

    public function store(StoreRosterRequest $request, CreateRoster $action): JsonResponse
    {
        $this->authorize('create', Roster::class);

        $ward = Ward::query()->whereKey($request->string('ward_id')->toString())->first();

        if ($ward === null) {
            return ProblemResponse::make(404, ErrorCode::ASSIGNMENT_TARGET_NOT_FOUND, 'Ward not found', 'The ward was not found.');
        }

        $author = $request->user();

        if (! $author instanceof User) {
            abort(401);
        }

        $roster = $action(
            $author,
            $ward,
            $request->string('period_start')->toString(),
            $request->string('period_end')->toString(),
        );
        $roster->load('ward');

        return response()->json([
            'data' => [
                'id' => $roster->id,
                'status' => $roster->status,
                'lock_version' => $roster->lock_version,
                'period_start' => $roster->period_start->toDateString(),
                'period_end' => $roster->period_end->toDateString(),
                'ward' => [
                    'id' => $roster->ward->id,
                    'code' => $roster->ward->code,
                    'name' => $roster->ward->name,
                ],
            ],
        ], 201);
    }

    public function show(Roster $roster, RosterGridQuery $query): JsonResponse
    {
        $this->authorize('view', $roster);

        return response()->json(['data' => $query->for($roster)]);
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
