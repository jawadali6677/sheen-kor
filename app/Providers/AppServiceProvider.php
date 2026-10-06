<?php

namespace App\Providers;

use App\Contracts\RewardedAdVerifier;
use App\Contracts\StripeCheckoutGateway;
use App\Enums\Permission;
use App\Models\Alert;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Support\CashierStripeCheckoutGateway;
use App\Support\UnavailableRewardedAdVerifier;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(RewardedAdVerifier::class, UnavailableRewardedAdVerifier::class);
        $this->app->bind(StripeCheckoutGateway::class, CashierStripeCheckoutGateway::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'post' => Post::class,
            'alert' => Alert::class,
            'user' => User::class,
        ]);

        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, function (User $user) use ($permission): bool {
                return $user->hasPermission($permission);
            });
        }

        $this->configureCheckoutRateLimiter();
        $this->configureQualifiedViewRateLimiter();
        $this->configureApiRateLimiters();

        Event::listen(function (PasswordReset $event): void {
            if ($event->user instanceof User) {
                $event->user->tokens()->delete();
            }
        });

        View::composer('layouts.partials.quick-post-composer', function ($view): void {
            $view->with(
                'composerCategories',
                Category::query()->where('status', true)->orderBy('name')->get(),
            );
        });
    }

    /**
     * Named limiter `checkout` (30/min per user) for Confirm payment / Pay retries.
     *
     * Signature: authenticated user id, or IP when unauthenticated.
     * Stored key (default hashing): md5('checkout'.$userId)
     * Unhashed form: checkout:{userId}
     *
     * Local unblock: php artisan cache:clear
     * Redis: redis-cli FLUSHDB when CACHE_STORE=redis
     */
    private function configureCheckoutRateLimiter(): void
    {
        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(30)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    $seconds = max(1, (int) ($headers['Retry-After'] ?? 60));
                    $message = 'Too many payment attempts. Please wait '.$seconds.' seconds and try again.';

                    if (app()->isLocal()) {
                        $message .= ' To unblock local testing immediately, run `php artisan cache:clear`. If CACHE_STORE=redis, you can also flush Redis.';
                    }

                    return back()
                        ->with('error', $message)
                        ->withHeaders($headers);
                });
        });
    }

    /**
     * JSON limiters for the mobile API. They are separate from the website
     * login throttle so an app lockout does not redirect with a flash message.
     */
    private function configureApiRateLimiters(): void
    {
        RateLimiter::for('api-login', function (Request $request): Limit {
            $email = Str::lower($request->string('email')->toString());

            return Limit::perMinute(5)->by(Str::transliterate($email.'|'.$request->ip()));
        });

        RateLimiter::for('api-register', function (Request $request): Limit {
            return Limit::perMinute(5)->by((string) $request->ip());
        });

        RateLimiter::for('api-forgot', function (Request $request): Limit {
            return Limit::perMinute(5)->by((string) $request->ip());
        });
    }

    /**
     * Feed qualified-view beacons. Authenticated, so the key is the user id.
     */
    private function configureQualifiedViewRateLimiter(): void
    {
        RateLimiter::for('qualified-views', function (Request $request): Limit {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}
