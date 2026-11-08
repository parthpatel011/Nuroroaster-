<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Identity\Actions\AttemptLogin;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Problem\ProblemResponse;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\SessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function store(LoginRequest $request, AttemptLogin $login, TenantContext $context): JsonResponse
    {
        try {
            $membership = $login($request->emailAddress(), $request->string('password')->toString());

            if ($membership === null) {
                return ProblemResponse::make(
                    401,
                    'UNAUTHENTICATED',
                    'Sign-in failed',
                    'Those credentials were not accepted.',
                );
            }

            $request->session()->regenerate();
            $request->session()->put('membership_id', $membership->id);
            $membership->load(['tenant', 'user']);

            return (new SessionResource($membership))->response();
        } finally {
            $context->reset();
        }
    }

    public function destroy(Request $request, TenantContext $context): JsonResponse
    {
        try {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json(['data' => ['signed_out' => true]]);
        } finally {
            $context->reset();
        }
    }
}
