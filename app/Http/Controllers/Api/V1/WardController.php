<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Organisation\Actions\CreateWard;
use App\Domain\Organisation\Models\Ward;
use App\Domain\Shared\Support\PageSize;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWardRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Ward::class);

        $page = Ward::query()->orderBy('name')->paginate(PageSize::from($request));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Ward $ward): array => $this->payload($ward))->values(),
            'meta' => $this->meta($page),
        ]);
    }

    public function store(StoreWardRequest $request, CreateWard $action): JsonResponse
    {
        $this->authorize('create', Ward::class);

        $ward = $action(
            $request->string('code')->toString(),
            $request->string('name')->toString(),
        );

        return response()->json(['data' => $this->payload($ward)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Ward $ward): array
    {
        return [
            'id' => $ward->id,
            'code' => $ward->code,
            'name' => $ward->name,
            'status' => $ward->status,
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
