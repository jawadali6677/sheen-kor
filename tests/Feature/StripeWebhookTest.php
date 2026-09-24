<?php

namespace Tests\Feature;

use App\Contracts\StripeCheckoutGateway;
use App\Enums\ListingPromotionStatus;
use App\Enums\MonetizationPackageType;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PostBoostStatus;
use App\Enums\UserVerificationStatus;
use App\Models\ListingPromotion;
use App\Models\MarketListing;
use App\Models\MonetizationPackage;
use App\Models\MonetizationSetting;
use App\Models\Order;
use App\Models\Post;
use App\Models\PostBoost;
use App\Models\User;
use App\Models\UserVerification;
use App\Notifications\GreenTickNeedsReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeStripeCheckoutGateway;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_checkout_completed_webhook_marks_the_order_paid_and_activates_a_boost(): void
    {
        [$order, $post] = $this->pendingBoostOrder();

        $this->postStripeWebhook($this->completedPayload($order, 'evt_boost_1'))
            ->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->payments()->first()->status);
        $this->assertTrue($post->fresh()->hasActiveBoost());
        $this->assertSame(1, PostBoost::query()->currentlyActive()->count());
    }

    public function test_a_signed_checkout_completed_webhook_activates_a_listing_promotion(): void
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');
        $package->forceFill(['price' => '9.00'])->save();

        $this->actingAs($user)
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id]);

        $order = Order::query()->firstOrFail();

        $this->postStripeWebhook($this->completedPayload($order, 'evt_listing_1'))
            ->assertOk();

        $this->assertTrue($listing->fresh()->hasActivePromotion());
        $this->assertSame(ListingPromotionStatus::Active, ListingPromotion::query()->first()->status);
    }

    public function test_paid_green_tick_moves_to_review_and_activates_when_review_is_disabled(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->makeEligible();
        $package = $this->enableGreenTickPackages()->firstWhere('slug', 'green_tick_monthly');
        $package->forceFill(['price' => '12.00'])->save();

        $this->actingAs($user)
            ->post(route('green-tick.store'), ['package_id' => $package->id]);

        $order = Order::query()->firstOrFail();

        $this->postStripeWebhook($this->completedPayload($order, 'evt_tick_1'))
            ->assertOk();

        $this->assertSame(UserVerificationStatus::PendingReview, UserVerification::query()->first()->status);
        Notification::assertSentTo($admin, GreenTickNeedsReview::class);

        MonetizationSetting::query()->where('key', 'green_tick_requires_review')->update(['value' => '0']);
        $other = User::factory()->create();
        $this->actingAs($other)
            ->post(route('green-tick.store'), ['package_id' => $package->id]);

        $second = Order::query()->latest('id')->firstOrFail();
        $this->postStripeWebhook($this->completedPayload($second, 'evt_tick_2'))
            ->assertOk();

        $this->assertTrue($other->fresh()->hasActiveGreenTick());
    }

    public function test_duplicate_checkout_completed_webhooks_do_not_double_activate(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $payload = $this->completedPayload($order, 'evt_boost_dup');

        $this->postStripeWebhook($payload)->assertOk();
        $this->postStripeWebhook($payload)->assertOk();

        $this->assertSame(1, PostBoost::query()->currentlyActive()->count());
        $this->assertSame(1, Order::query()->where('status', OrderStatus::Paid)->count());
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertTrue($post->fresh()->hasActiveBoost());
    }

    public function test_invalid_webhook_signatures_are_rejected(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $payload = json_encode($this->completedPayload($order, 'evt_bad_sig'));

        config([
            'cashier.key' => 'pk_test_123',
            'cashier.secret' => 'sk_test_123',
            'cashier.webhook.secret' => 'whsec_test_123',
        ]);

        $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't=1,v1=not-valid',
        ], $payload)
            ->assertForbidden();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertFalse($post->fresh()->hasActiveBoost());
    }

    public function test_expired_and_failed_payments_do_not_activate_entitlements(): void
    {
        [$order, $post] = $this->pendingBoostOrder();

        $this->postStripeWebhook([
            'id' => 'evt_expired_1',
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'id' => $order->payments()->first()->provider_reference,
                    'object' => 'checkout.session',
                    'payment_status' => 'unpaid',
                    'client_reference_id' => (string) $order->id,
                    'metadata' => ['order_id' => (string) $order->id],
                ],
            ],
        ])->assertOk();

        $this->assertSame(OrderStatus::Failed, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Failed, $order->payments()->first()->status);
        $this->assertSame(PostBoostStatus::Cancelled, PostBoost::query()->first()->status);
        $this->assertFalse($post->fresh()->hasActiveBoost());
    }

    public function test_an_old_expired_checkout_session_does_not_fail_an_order_with_a_newer_session(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $staleSessionId = $order->payments()->first()->provider_reference;

        $this->fakeStripe();

        $this->actingAs($order->user)
            ->post(route('orders.pay', $order))
            ->assertRedirect('https://checkout.stripe.test/cs_test_123');

        $this->assertSame('cs_test_123', $order->payments()->latest('id')->first()->provider_reference);

        $this->postStripeWebhook([
            'id' => 'evt_expired_stale',
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'id' => $staleSessionId,
                    'object' => 'checkout.session',
                    'payment_status' => 'unpaid',
                    'client_reference_id' => (string) $order->id,
                    'metadata' => ['order_id' => (string) $order->id],
                ],
            ],
        ])->assertOk();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $order->payments()->latest('id')->first()->status);
        $this->assertSame(PostBoostStatus::Pending, PostBoost::query()->first()->status);
        $this->assertFalse($post->fresh()->hasActiveBoost());

        $this->postStripeWebhook([
            'id' => 'evt_expired_current',
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'object' => 'checkout.session',
                    'payment_status' => 'unpaid',
                    'client_reference_id' => (string) $order->id,
                    'metadata' => ['order_id' => (string) $order->id],
                ],
            ],
        ])->assertOk();

        $this->assertSame(OrderStatus::Failed, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Failed, $order->payments()->latest('id')->first()->status);
        $this->assertSame(PostBoostStatus::Cancelled, PostBoost::query()->first()->status);
    }

    public function test_payment_intent_succeeded_does_not_fulfill_the_order(): void
    {
        [$order, $post] = $this->pendingBoostOrder();

        $this->postStripeWebhook([
            'id' => 'evt_pi_1',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_123',
                    'object' => 'payment_intent',
                    'metadata' => ['order_id' => (string) $order->id],
                ],
            ],
        ])->assertOk();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertFalse($post->fresh()->hasActiveBoost());
    }

    public function test_refund_webhooks_do_not_revoke_active_entitlements(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $this->postStripeWebhook($this->completedPayload($order, 'evt_paid_then_refund'))
            ->assertOk();

        $this->postStripeWebhook([
            'id' => 'evt_refund_1',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_123',
                    'object' => 'charge',
                    'metadata' => ['order_id' => (string) $order->id],
                ],
            ],
        ])->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertTrue($post->fresh()->hasActiveBoost());
    }

    public function test_admin_mark_paid_still_works_for_a_pending_stripe_order(): void
    {
        $admin = User::factory()->admin()->create();
        [$order, $post] = $this->pendingBoostOrder();

        $this->actingAs($admin)
            ->post(route('admin.monetization.orders.mark-paid', $order))
            ->assertRedirect(route('admin.monetization.orders.show', $order));

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertTrue($post->fresh()->hasActiveBoost());
    }

    /**
     * @return array{0: Order, 1: Post}
     */
    private function pendingBoostOrder(): array
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');
        $package->forceFill(['price' => '4.50'])->save();

        $this->actingAs($user)
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id]);

        $order = Order::query()->firstOrFail();
        $order->payments()->first()->forceFill([
            'provider' => PaymentProvider::Stripe,
            'provider_reference' => 'cs_test_order_'.$order->id,
        ])->save();

        return [$order->fresh(['boost.post', 'payments']), $post];
    }

    /**
     * @return array<string, mixed>
     */
    private function completedPayload(Order $order, string $eventId): array
    {
        return [
            'id' => $eventId,
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => $order->payments()->first()->provider_reference,
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'client_reference_id' => (string) $order->id,
                    'metadata' => ['order_id' => (string) $order->id],
                    'payment_intent' => 'pi_'.$eventId,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postStripeWebhook(array $payload)
    {
        config([
            'cashier.key' => 'pk_test_123',
            'cashier.secret' => 'sk_test_123',
            'cashier.webhook.secret' => 'whsec_test_123',
        ]);

        $body = json_encode($payload);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test_123');

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $body);
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

    private function enableGreenTickPackages()
    {
        MonetizationPackage::query()
            ->where('type', MonetizationPackageType::GreenTick)
            ->update(['is_enabled' => true]);

        return MonetizationPackage::query()
            ->where('type', MonetizationPackageType::GreenTick)
            ->get();
    }

    private function makeEligible(): void
    {
        MonetizationSetting::query()->where('key', 'eligibility_min_followers')->update(['value' => '0']);
        MonetizationSetting::query()->where('key', 'eligibility_min_posts')->update(['value' => '0']);
        MonetizationSetting::query()->where('key', 'eligibility_min_published_posts')->update(['value' => '0']);
        MonetizationSetting::query()->where('key', 'eligibility_min_qualified_views_30d')->update(['value' => '0']);
    }
}
