<?php

use App\Domain\Rosters\Exceptions\RosterDomainException;
use App\Domain\Shared\Support\PostgresExceptionMapper;
use App\Domain\Tenancy\Middleware\SetTenantContext;
use App\Http\Problem\ProblemResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->prefix('api/v1')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (): null => null);
        $middleware->alias([
            'tenant' => SetTenantContext::class,
        ]);
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            SetTenantContext::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (RosterDomainException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ProblemResponse::make(
                $exception->status,
                $exception->errorCode,
                'Roster change rejected',
                $exception->getMessage(),
            );
        });

        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $mapped = PostgresExceptionMapper::map($exception);

            if ($mapped !== null) {
                return ProblemResponse::make($mapped->status, $mapped->errorCode, 'Roster change rejected', $mapped->getMessage());
            }

            return ProblemResponse::make(500, 'DATABASE_ERROR', 'Database error', 'The change could not be saved.');
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ProblemResponse::make(404, 'NOT_FOUND', 'Not found', 'The requested record was not found.');
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return ProblemResponse::make(401, 'UNAUTHENTICATED', 'Authentication required', 'Sign in to continue.');
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ProblemResponse::make(403, 'FORBIDDEN', 'Forbidden', 'You do not have permission to do that.');
        });

        $exceptions->render(function (HttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $message = $exception->getMessage();

            if ($status === 404 || str_contains($message, 'SQL') || str_contains($message, 'App\\')) {
                $message = $status === 404 ? 'The requested record was not found.' : 'Request failed.';
            }

            if ($message === '') {
                $message = 'Request failed.';
            }

            return ProblemResponse::make(
                $status,
                $status === 403 ? 'FORBIDDEN' : 'HTTP_ERROR',
                'Request failed',
                $message,
            );
        });
    })->create();
