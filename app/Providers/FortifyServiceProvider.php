<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\Auth\GenericPasswordResetLinkResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Routes registered by Fortify that aren't otherwise throttled and
     * are reachable by guests, mapped to the rate limiter that should
     * guard them.
     *
     * @var array<string, string>
     */
    private const THROTTLED_GUEST_ROUTES = [
        'password.email' => 'password-reset-link',
        'password.update' => 'password-reset',
        'register.store' => 'register',
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Both outcomes of a "forgot password" request must be
        // indistinguishable, otherwise the endpoint becomes a user
        // enumeration oracle (existing vs. non-existing email addresses
        // would render different messages).
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, GenericPasswordResetLinkResponse::class);
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, GenericPasswordResetLinkResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureRouteThrottling();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(function (Request $request) {
            // First launch: nobody to log in as yet, so send straight to
            // the one-time admin setup instead of a login form with no
            // way to sign up.
            if (! User::query()->exists()) {
                return redirect()->route('register');
            }

            return Inertia::render('auth/Login', [
                'canResetPassword' => Features::enabled(Features::resetPasswords()),
                'status' => $request->session()->get('status'),
            ]);
        });

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(function () {
            // MINITOR is single-admin: registration only exists to create
            // that one account on first launch. Once it exists, this route
            // is dead - send visitors to the login page instead.
            if (User::query()->exists()) {
                return redirect()->route('login');
            }

            return Inertia::render('auth/Register', [
                'passwordRules' => Password::defaults()->toPasswordRulesString(),
            ]);
        });

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('password-reset-link', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input(Fortify::email())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }

    /**
     * Attach rate limiting to the guest-facing Fortify routes that ship
     * without any throttle middleware of their own. This is done after the
     * application has booted (rather than in the routes file) because
     * Fortify owns and registers these routes itself.
     */
    private function configureRouteThrottling(): void
    {
        $this->app->booted(function () {
            $routes = Route::getRoutes();

            // Fortify's own routes are registered fluently (->name() is
            // called after the route is already added to the collection),
            // so the name lookup table isn't populated until it's rebuilt.
            $routes->refreshNameLookups();

            foreach (self::THROTTLED_GUEST_ROUTES as $routeName => $limiter) {
                $routes->getByName($routeName)?->middleware('throttle:'.$limiter);
            }
        });
    }
}
