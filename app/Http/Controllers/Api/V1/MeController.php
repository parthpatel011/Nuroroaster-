<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SessionResource;
use Illuminate\Http\JsonResponse;

class MeController extends Controller
{
    public function __invoke(TenantContext $context): JsonResponse
    {
        $membership = $context->membership();
        abort_if($membership === null, 403);
        $membership->load('tenant', 'user');

        return (new SessionResource($membership))->response();
    }
}
