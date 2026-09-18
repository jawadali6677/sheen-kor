<?php

namespace Tests\Feature;

use App\Contracts\StripeCheckoutGateway;
use App\Enums\MonetizationPackageType;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
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

    public function test_success_url_does_not_mark_the_order_paid(): void
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
            ->assertSee('Stripe is confirming this payment');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertFalse($post->fresh()->hasActiveBoost());
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
}
