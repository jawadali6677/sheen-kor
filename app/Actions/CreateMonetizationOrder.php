<?php

namespace App\Actions;

use App\Enums\ListingPromotionSource;
use App\Enums\ListingPromotionStatus;
use App\Enums\MarketListingStatus;
use App\Enums\MonetizationPackageType;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PostBoostSource;
use App\Enums\PostBoostStatus;
use App\Enums\UserVerificationSource;
use App\Enums\UserVerificationStatus;
use App\Models\MarketListing;
use App\Models\MonetizationPackage;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateMonetizationOrder
{
    public function handle(User $user, MonetizationPackage $package, ?Post $post = null, ?MarketListing $listing = null): ?Order
    {
        if ($package->duration_days === null || $package->duration_days < 1) {
            return null;
        }

        return DB::transaction(function () use ($user, $package, $post, $listing): ?Order {
            return match ($package->type) {
                MonetizationPackageType::GreenTick => $this->createGreenTickOrder($user, $package),
                MonetizationPackageType::PostBoost => $this->createBoostOrder($user, $package, $post),
                MonetizationPackageType::ListingPromotion => $this->createListingOrder($user, $package, $listing),
                default => null,
            };
        });
    }

    private function createGreenTickOrder(User $user, MonetizationPackage $package): ?Order
    {
        $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

        if ($locked->hasOpenGreenTickRequest()) {
            return null;
        }

        $order = $this->createPendingOrder($locked, $package);

        $locked->greenTickVerifications()->create([
            'package_id' => $package->id,
            'order_id' => $order->id,
            'status' => UserVerificationStatus::PendingPayment,
            'source' => UserVerificationSource::Request,
            'package_name' => $package->name,
            'package_slug' => $package->slug,
            'duration_days' => $package->duration_days,
            'price' => $package->price,
            'currency' => $package->currency,
        ]);

        return $order;
    }

    private function createBoostOrder(User $user, MonetizationPackage $package, ?Post $post): ?Order
    {
        if ($post === null) {
            return null;
        }

        $locked = Post::query()->whereKey($post->id)->lockForUpdate()->firstOrFail();

        if ($locked->status !== 'published' || $locked->user_id !== $user->id || $locked->hasOpenBoost()) {
            return null;
        }

        $order = $this->createPendingOrder($user, $package, post: $locked);

        $locked->boosts()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'order_id' => $order->id,
            'status' => PostBoostStatus::Pending,
            'source' => PostBoostSource::Request,
            'package_type' => $package->type->value,
            'package_name' => $package->name,
            'package_slug' => $package->slug,
            'duration_days' => $package->duration_days,
            'price' => $package->price,
            'currency' => $package->currency,
        ]);

        return $order;
    }

    private function createListingOrder(User $user, MonetizationPackage $package, ?MarketListing $listing): ?Order
    {
        if ($listing === null || blank($package->placement)) {
            return null;
        }

        $locked = MarketListing::query()->whereKey($listing->id)->lockForUpdate()->firstOrFail();

        if ($locked->user_id !== $user->id || $locked->status !== MarketListingStatus::Published || $locked->hasOpenPromotion()) {
            return null;
        }

        $order = $this->createPendingOrder($user, $package, listing: $locked);

        $locked->promotions()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'order_id' => $order->id,
            'status' => ListingPromotionStatus::Pending,
            'source' => ListingPromotionSource::Request,
            'placement' => $package->placement,
            'package_type' => $package->type->value,
            'package_name' => $package->name,
            'package_slug' => $package->slug,
            'duration_days' => $package->duration_days,
            'price' => $package->price,
            'currency' => $package->currency,
        ]);

        return $order;
    }

    private function createPendingOrder(User $user, MonetizationPackage $package, ?Post $post = null, ?MarketListing $listing = null): Order
    {
        $order = Order::query()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'post_id' => $post?->id,
            'market_listing_id' => $listing?->id,
            'status' => OrderStatus::Pending,
            'amount' => $package->price,
            'currency' => $package->currency,
            'snapshot' => [
                'name' => $package->name,
                'slug' => $package->slug,
                'type' => $package->type->value,
                'duration_days' => $package->duration_days,
                'placement' => $package->placement,
                'price' => $package->price,
            ],
        ]);

        $cents = (int) round(((float) $package->price) * 100);
        $provider = stripe_checkout_is_configured() && $cents > 0
            ? PaymentProvider::Stripe
            : PaymentProvider::Manual;

        $order->payments()->create([
            'provider' => $provider,
            'provider_reference' => (string) Str::uuid(),
            'idempotency_key' => Payment::checkoutIdempotencyKey($order->id),
            'amount' => $order->amount,
            'currency' => $order->currency,
            'status' => PaymentStatus::Pending,
        ]);

        return $order;
    }
}
