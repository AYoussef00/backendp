<?php

namespace App\Providers;

use App\Models\Server;
use App\Models\Website;
use App\Policies\ServerPolicy;
use App\Policies\WebsitePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAuthorization(): void
    {
        Gate::policy(Server::class, ServerPolicy::class);
        Gate::policy(Website::class, WebsitePolicy::class);
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('agent', function (Request $request) {
            $key = $request->header('X-Agent-Id') ?: $request->ip();

            return Limit::perMinute(120)->by((string) $key);
        });
    }
}
