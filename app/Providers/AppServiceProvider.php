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
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
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
    }
}
