<?php

namespace App\Providers;

use App\Contracts\Audit\AuditLoggerInterface;
use App\Contracts\Phone\PhoneNumberServiceInterface;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Phone\LibPhoneNumberService;
use App\Support\Access\RoleRegistry;
use App\Support\RequestId;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use libphonenumber\PhoneNumberUtil;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One correlation id per request (or job) process.
        $this->app->singleton(RequestId::class);

        // libphonenumber's metadata tables are large; build them once per
        // process instead of on every resolution.
        $this->app->singleton(
            PhoneNumberUtil::class,
            static fn (): PhoneNumberUtil => PhoneNumberUtil::getInstance(),
        );

        $this->app->singleton(PhoneNumberServiceInterface::class, LibPhoneNumberService::class);
        $this->app->singleton(AuditLoggerInterface::class, AuditLogger::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configureAuthorization();
    }

    private function configureRateLimiting(): void
    {
        foreach (['login','otp_request','otp_verify'] as $limiter) {
            RateLimiter::for($limiter, fn (Request $request): Limit => Limit::perMinute((int)config('access.rate_limits.'.$limiter))->by($limiter.':'.$request->ip()));
        }

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute((int) config('access.rate_limits.api'))
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        RateLimiter::for('webhooks', function (Request $request): Limit {
            return Limit::perMinute((int) config('access.rate_limits.webhooks'))
                ->by($request->ip());
        });
    }

    private function configureAuthorization(): void
    {
        /*
         * Super-admin bypass.
         *
         * Returning true grants the ability; returning null (not false) lets the
         * normal policies and permission checks run for everyone else, so this
         * does not disable authorization for ordinary users.
         *
         * The role is checked rather than a permission, which is why
         * RoleRegistry grants super-admin no permissions: its authority cannot
         * drift as new permissions are added.
         */
        Gate::before(function ($user, string $ability): ?bool {
            if ($user instanceof User && $user->isAdmin() && $user->isActive() && $user->hasRole(RoleRegistry::SUPER_ADMIN)) {
                return true;
            }

            return null;
        });
    }
}
