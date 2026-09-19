<?php

namespace Tests\Feature;

use App\Contracts\StripeCheckoutGateway;
use App\Enums\MonetizationPackageType;
use App\Enums\PaymentProvider;
use App\Models\MarketListing;
use App\Models\MonetizationPackage;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\FakeStripeCheckoutGateway;
use Tests\TestCase;

class CheckoutRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_pay_retries_are_allowed_beyond_ten_attempts_per_minute(): void
    {
        $this->fakeStripe();
        [$user, $order] = $this->pendingStripeOrder();

        for ($attempt = 1; $attempt <= 11; $attempt++) {
            $this->actingAs($user)
                ->from(route('orders.show', $order))
                ->post(route('orders.pay', $order))
                ->assertRedirect('https://checkout.stripe.test/cs_test_123');
        }
    }

    public function test_confirm_payment_throttle_redirects_with_a_flash_instead_of_raw_429(): void
    {
        $this->fakeStripe();
        [$user, $order] = $this->pendingStripeOrder();

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->actingAs($user)
                ->from(route('orders.show', $order))
                ->post(route('orders.pay', $order))
                ->assertRedirect('https://checkout.stripe.test/cs_test_123');
        }

        $response = $this->actingAs($user)
            ->from(route('orders.show', $order))
            ->post(route('orders.pay', $order));

        $response
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('error');

        $this->assertSame(302, $response->status());
        $this->assertStringContainsString('Too many payment attempts', (string) session('error'));
        $this->assertStringNotContainsString('php artisan cache:clear', (string) session('error'));
    }

    public function test_listing_promotion_confirm_uses_the_same_checkout_limiter(): void
    {
        $this->fakeStripe();
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');
        $package->forceFill(['price' => '6.50'])->save();

        $this->actingAs($user)
            ->from(route('market.promote.create', $listing))
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id])
            ->assertRedirect('https://checkout.stripe.test/cs_test_123');

        for ($attempt = 2; $attempt <= 30; $attempt++) {
            $this->actingAs($user)
                ->from(route('market.promote.create', $listing))
                ->post(route('market.promote.store', $listing), ['package_id' => $package->id])
                ->assertRedirect(route('market.promote.create', $listing))
                ->assertSessionHas('error');
        }

        $response = $this->actingAs($user)
            ->from(route('market.promote.create', $listing))
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id]);

        $response
            ->assertRedirect(route('market.promote.create', $listing))
            ->assertSessionHas('error');

        $this->assertSame(302, $response->status());
        $this->assertStringContainsString('Too many payment attempts', (string) session('error'));
    }

    public function test_checkout_throttle_is_keyed_per_user(): void
    {
        $this->fakeStripe();
        [$firstUser, $firstOrder] = $this->pendingStripeOrder();
        [$secondUser, $secondOrder] = $this->pendingStripeOrder();

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->actingAs($firstUser)
                ->from(route('orders.show', $firstOrder))
                ->post(route('orders.pay', $firstOrder))
                ->assertRedirect('https://checkout.stripe.test/cs_test_123');
        }

        $this->actingAs($secondUser)
            ->from(route('orders.show', $secondOrder))
            ->post(route('orders.pay', $secondOrder))
            ->assertRedirect('https://checkout.stripe.test/cs_test_123');
    }

    public function test_clearing_the_cache_unblocks_checkout_after_the_limit(): void
    {
        $this->fakeStripe();
        [$user, $order] = $this->pendingStripeOrder();

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->actingAs($user)
                ->from(route('orders.show', $order))
                ->post(route('orders.pay', $order))
                ->assertRedirect('https://checkout.stripe.test/cs_test_123');
        }

        Artisan::call('cache:clear');

        $this->actingAs($user)
            ->from(route('orders.show', $order))
            ->post(route('orders.pay', $order))
            ->assertRedirect('https://checkout.stripe.test/cs_test_123');
    }

    public function test_local_checkout_throttle_flash_explains_how_to_clear_the_cache(): void
    {
        $this->app['env'] = 'local';
        $this->fakeStripe();
        [$user, $order] = $this->pendingStripeOrder();

        for ($attempt = 1; $attempt <= 30; $attempt++) {
            $this->actingAs($user)
                ->from(route('orders.show', $order))
                ->post(route('orders.pay', $order))
                ->assertRedirect('https://checkout.stripe.test/cs_test_123');
        }

        $this->actingAs($user)
            ->from(route('orders.show', $order))
            ->post(route('orders.pay', $order))
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('error');

        $this->assertStringContainsString('php artisan cache:clear', (string) session('error'));
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function pendingStripeOrder(): array
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');
        $package->forceFill(['price' => '6.50'])->save();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'market_listing_id' => $listing->id,
            'amount' => '6.50',
            'currency' => 'USD',
            'snapshot' => [
                'name' => $package->name,
                'slug' => $package->slug,
                'type' => $package->type->value,
                'duration_days' => $package->duration_days,
                'placement' => $package->placement,
                'price' => '6.50',
            ],
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'provider' => PaymentProvider::Stripe,
            'amount' => '6.50',
            'currency' => 'USD',
        ]);

        return [$user, $order];
    }

    private function fakeStripe(): FakeStripeCheckoutGateway
    {
        config([
            'cashier.key' => 'pk_test_123',
            'cashier.secret' => 'sk_test_123',
            'cashier.webhook.secret' => 'whsec_test_123',
        ]);

        $gateway = new FakeStripeCheckoutGateway;
        $this->app->instance(StripeCheckoutGateway::class, $gateway);

        return $gateway;
    }

    /**
     * @return \Illuminate\Support\Collection<int, MonetizationPackage>
     */
    private function enablePromotionPackages()
    {
        MonetizationPackage::query()
            ->where('type', MonetizationPackageType::ListingPromotion)
            ->update(['is_enabled' => true]);

        return MonetizationPackage::query()
            ->where('type', MonetizationPackageType::ListingPromotion)
            ->get();
    }
}
