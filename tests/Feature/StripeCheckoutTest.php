<?php

namespace Tests\Feature;

use App\Contracts\StripeCheckoutGateway;
use App\Enums\MonetizationPackageType;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\MarketListing;
use App\Models\MonetizationPackage;
use App\Models\Order;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeStripeCheckoutGateway;
use Tests\TestCase;

class StripeCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_packages_redirect_to_stripe_using_the_order_snapshot_amount(): void
    {
        $gateway = $this->fakeStripe();
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');
        $package->forceFill(['price' => '4.50', 'name' => 'Post Boost - 1 Day'])->save();

        $this->actingAs($user)
            ->post(route('posts.boost.store', $post), [
                'package_id' => $package->id,
                'amount' => '99.00',
                'price' => '99.00',
            ])
            ->assertRedirect('https://checkout.stripe.test/cs_test_123');

        $order = Order::query()->firstOrFail();
        $payment = $order->payments()->first();

        $this->assertSame('4.50', $order->amount);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentProvider::Stripe, $payment->provider);
        $this->assertSame('cs_test_123', $payment->provider_reference);
        $this->assertFalse($post->fresh()->hasActiveBoost());
        $this->assertSame(450, $gateway->charges[0]['amount_cents']);
        $this->assertSame('Post Boost - 1 Day', $gateway->charges[0]['name']);
    }

    public function test_success_url_leaves_the_order_pending_when_the_checkout_session_is_unpaid(): void
    {
        $this->fakeStripe();
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');
        $package->forceFill(['price' => '4.50'])->save();

        $this->actingAs($user)
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('orders.show', $order).'?checkout=success&session_id=cs_test_123')
            ->assertOk()
            ->assertSee('Confirming payment with Stripe')
            ->assertSee('still pending and is not paid');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertFalse($post->fresh()->hasActiveBoost());
    }

    public function test_success_url_marks_the_order_paid_when_the_checkout_session_is_paid(): void
    {
        $gateway = $this->fakeStripe();
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');
        $package->forceFill(['price' => '9.00'])->save();

        $this->travelTo('2026-09-19 12:00:00');

        $this->actingAs($user)
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id])
            ->assertRedirect('https://checkout.stripe.test/cs_test_123');

        $order = Order::query()->firstOrFail();
        $gateway->markSessionPaid('cs_test_123');

        $this->actingAs($user)
            ->get(route('orders.show', $order).'?checkout=success&session_id=cs_test_123')
            ->assertRedirect(route('market.show', $listing))
            ->assertSessionHas('success', 'Payment confirmed. Promoted until Sep 26, 2026.');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->payments()->first()->status);
        $this->assertTrue($listing->fresh()->hasActivePromotion());

        $this->actingAs($user)
            ->get(route('market.show', $listing))
            ->assertOk()
            ->assertSee('Promoted until Sep 26, 2026')
            ->assertDontSee('Pending payment');
    }

    public function test_success_url_does_not_mark_another_orders_session_as_paid(): void
    {
        $gateway = $this->fakeStripe();
        $user = User::factory()->create();
        $firstListing = MarketListing::factory()->create(['user_id' => $user->id]);
        $secondListing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');
        $package->forceFill(['price' => '9.00'])->save();

        $this->actingAs($user)
            ->post(route('market.promote.store', $firstListing), ['package_id' => $package->id]);

        $firstOrder = Order::query()->firstOrFail();
        $gateway->nextSessionId = 'cs_test_other';
        $gateway->nextUrl = 'https://checkout.stripe.test/cs_test_other';

        $this->actingAs($user)
            ->post(route('market.promote.store', $secondListing), ['package_id' => $package->id]);

        $secondOrder = Order::query()->where('market_listing_id', $secondListing->id)->firstOrFail();
        $gateway->markSessionPaid('cs_test_other');

        $this->actingAs($user)
            ->get(route('orders.show', $firstOrder).'?checkout=success&session_id=cs_test_other')
            ->assertOk()
            ->assertSee('Confirming payment with Stripe');

        $this->assertSame(OrderStatus::Pending, $firstOrder->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $secondOrder->fresh()->status);
        $this->assertFalse($firstListing->fresh()->hasActivePromotion());
        $this->assertFalse($secondListing->fresh()->hasActivePromotion());
    }

    public function test_cancel_url_leaves_the_order_pending_so_payment_can_be_retried(): void
    {
        $this->fakeStripe();
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');
        $package->forceFill(['price' => '4.50'])->save();

        $this->actingAs($user)
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('orders.show', $order).'?checkout=cancelled')
            ->assertOk()
            ->assertSee('Checkout was cancelled');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);

        $this->actingAs($user)
            ->post(route('orders.pay', $order))
            ->assertRedirect('https://checkout.stripe.test/cs_test_123');
    }

    public function test_zero_amount_and_missing_stripe_keys_stay_on_the_manual_pending_flow(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');

        $this->actingAs($user)
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id])
            ->assertRedirect(route('orders.show', Order::query()->first()));

        $order = Order::query()->firstOrFail();

        $this->assertSame('0.00', $order->amount);
        $this->assertSame(PaymentProvider::Manual, $order->payments()->first()->provider);
        $this->assertFalse($order->shouldChargeWithStripe());

        $this->configureStripe();
        $zeroPackage = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_7d');
        $otherPost = Post::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('posts.boost.store', $otherPost), ['package_id' => $zeroPackage->id])
            ->assertRedirect();

        $zeroOrder = Order::query()->where('post_id', $otherPost->id)->firstOrFail();
        $this->assertSame(PaymentProvider::Manual, $zeroOrder->payments()->first()->provider);
        $this->assertFalse($zeroOrder->shouldChargeWithStripe());
    }

    public function test_members_cannot_start_checkout_for_another_users_order(): void
    {
        $this->fakeStripe();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');
        $package->forceFill(['price' => '4.50'])->save();

        $this->actingAs($owner)
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($other)
            ->post(route('orders.pay', $order))
            ->assertForbidden();
    }

    private function fakeStripe(): FakeStripeCheckoutGateway
    {
        $this->configureStripe();
        $gateway = new FakeStripeCheckoutGateway;
        $this->app->instance(StripeCheckoutGateway::class, $gateway);

        return $gateway;
    }

    private function configureStripe(): void
    {
        config([
            'cashier.key' => 'pk_test_123',
            'cashier.secret' => 'sk_test_123',
            'cashier.webhook.secret' => 'whsec_test_123',
        ]);
    }

    private function enableBoostPackages()
    {
        MonetizationPackage::query()
            ->where('type', MonetizationPackageType::PostBoost)
            ->update(['is_enabled' => true]);

        return MonetizationPackage::query()
            ->where('type', MonetizationPackageType::PostBoost)
            ->get();
    }

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
