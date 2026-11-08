<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Organisation\Models\Ward;
use App\Domain\Rosters\Models\Roster;
use App\Domain\Shifts\Models\ShiftDefinition;
use App\Domain\Staff\Models\Employee;
use App\Domain\Tenancy\TenantContext;
use App\Policies\EmployeePolicy;
use App\Policies\RosterPolicy;
use App\Policies\ShiftDefinitionPolicy;
use App\Policies\WardPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        Model::preventLazyLoading(true);
        Model::preventSilentlyDiscardingAttributes(true);

        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Ward::class, WardPolicy::class);
        Gate::policy(ShiftDefinition::class, ShiftDefinitionPolicy::class);
        Gate::policy(Roster::class, RosterPolicy::class);

        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            if ($event->connection->getDriverName() === 'pgsql') {
                $event->connection->statement("SET TIME ZONE 'UTC'");
            }
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower((string) $request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });
    }
}
