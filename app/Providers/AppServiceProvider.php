<?php

namespace App\Providers;

use App\Models\NotificationSetting;
use App\Services\Mail\DynamicMailerFactory;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureDynamicMailer();

        // The remembered time-range preference (TimeRange::resolve) is tied
        // to whoever's browsing — clear it on logout so the next person to
        // log in (single-admin app, but still) starts from the default
        // rather than inheriting the previous session's choice.
        Event::listen(Logout::class, function () {
            Cookie::queue(Cookie::forget('minitor_range'));
        });
    }

    /**
     * Route Laravel's own built-in mail (password reset, email verification,
     * etc.) through the operator-configured provider from Settings >
     * Notifications, the same one issue-alert emails already use - without
     * this, those framework notifications silently fall back to whatever's
     * in MAIL_MAILER (typically "log" in this app, since config/mail.php has
     * no static provider of its own).
     *
     * Deliberately skipped in console: queue workers are long-running and
     * don't reboot between jobs, so config set here once at worker startup
     * would go stale if credentials change. Queued mail (e.g. issue alerts)
     * already applies fresh credentials per-job via DynamicMailerFactory;
     * this is only for mail sent synchronously within an HTTP request.
     */
    protected function configureDynamicMailer(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $settings = NotificationSetting::current();

        if (! $settings->enabled || ! $settings->isConfigured()) {
            return;
        }

        $mailer = app(DynamicMailerFactory::class)->apply($settings);

        config(['mail.default' => $mailer]);
    }

    /**
     * Configure rate limiting for API routes.
     */
    protected function configureRateLimiting(): void
    {
        // Keyed by project token when present so one project's traffic can't
        // exhaust another's allowance; falls back to IP for unauthenticated
        // or invalid-token requests, which also caps token-guessing attempts.
        RateLimiter::for('ingest', function (Request $request) {
            return Limit::perMinute(300)->by($request->bearerToken() ?? $request->ip());
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
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
}
