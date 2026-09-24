<?php

namespace App\Providers;

use App\Contracts\RewardedAdVerifier;
use App\Contracts\StripeCheckoutGateway;
use App\Enums\Permission;
use App\Models\Alert;
use App\Models\Post;
use App\Models\User;
use App\Support\CashierStripeCheckoutGateway;
use App\Support\UnavailableRewardedAdVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
}
