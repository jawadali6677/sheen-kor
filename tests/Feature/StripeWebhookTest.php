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
use ArrayObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeStripeCheckoutGateway;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_signed_checkout_completed_webhook_marks_the_order_paid_and_activates_a_boost(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $logged = $this->captureLogs();

        $this->postStripeWebhook($this->completedPayload($order, 'evt_boost_1'))
            ->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->payments()->first()->status);
        $this->assertTrue($post->fresh()->hasActiveBoost());
        $this->assertSame(1, PostBoost::query()->currentlyActive()->count());
        $this->assertLoggedReason($logged, 'Stripe webhook fulfilled', 'fulfilled', $order->id);
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
        $order->payments()->first()->forceFill([
            'provider' => PaymentProvider::Stripe,
            'provider_reference' => 'cs_test_listing_'.$order->id,
        ])->save();

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
        $order->payments()->first()->forceFill([
            'provider' => PaymentProvider::Stripe,
            'provider_reference' => 'cs_test_tick_'.$order->id,
        ])->save();

        $this->postStripeWebhook($this->completedPayload($order, 'evt_tick_1'))
            ->assertOk();

        $this->assertSame(UserVerificationStatus::PendingReview, UserVerification::query()->first()->status);
        Notification::assertSentTo($admin, GreenTickNeedsReview::class);

        MonetizationSetting::query()->where('key', 'green_tick_requires_review')->update(['value' => '0']);
        $other = User::factory()->create();
        $this->actingAs($other)
            ->post(route('green-tick.store'), ['package_id' => $package->id]);

        $second = Order::query()->latest('id')->firstOrFail();
        $second->payments()->first()->forceFill([
            'provider' => PaymentProvider::Stripe,
            'provider_reference' => 'cs_test_tick_'.$second->id,
        ])->save();
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

        $this->actingAs($order->user)
            ->post(route('orders.pay', $order))
            ->assertForbidden();

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_an_old_expired_checkout_session_does_not_fail_an_order_with_a_newer_session(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $staleSessionId = $order->payments()->first()->provider_reference;

        $gateway = $this->fakeStripe();
        $gateway->sessions[$staleSessionId] = [
            'id' => $staleSessionId,
            'status' => 'expired',
            'url' => null,
            'payment_status' => 'unpaid',
            'order_id' => (string) $order->id,
            'client_reference_id' => (string) $order->id,
            'payment_intent' => null,
            'payment_intent_status' => null,
        ];

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

        $gateway->sessions['cs_test_123']['status'] = 'expired';
        $gateway->sessions['cs_test_123']['payment_status'] = 'unpaid';

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

        $logged = $this->captureLogs();

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
        $this->assertLoggedReason($logged, 'Stripe webhook skipped', 'event_not_handled', null);
    }

    public function test_checkout_completed_with_an_integer_order_id_marks_the_order_paid(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $payload = $this->completedPayload($order, 'evt_int_order');
        $payload['data']['object']['metadata'] = ['order_id' => $order->id];
        $payload['data']['object']['client_reference_id'] = $order->id;

        $this->postStripeWebhook($payload)->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertTrue($post->fresh()->hasActiveBoost());
    }

    public function test_checkout_completed_without_metadata_uses_the_stored_session_id(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $payload = $this->completedPayload($order, 'evt_session_only');
        unset($payload['data']['object']['metadata'], $payload['data']['object']['client_reference_id']);

        $this->postStripeWebhook($payload)->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertTrue($post->fresh()->hasActiveBoost());
    }

    public function test_checkout_completed_falls_back_to_the_stored_session_when_the_metadata_order_is_missing(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $payload = $this->completedPayload($order, 'evt_missing_order');
        $payload['data']['object']['metadata'] = ['order_id' => 999999999];
        $payload['data']['object']['client_reference_id'] = '999999999';
        $gateway = $this->fakeStripe();
        $sessionId = $order->payments()->first()->provider_reference;
        $gateway->sessions[$sessionId] = $this->settledSession($order, $sessionId, 'pi_evt_missing_order');

        $this->postStripeWebhook($payload)->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertTrue($post->fresh()->hasActiveBoost());
    }

    public function test_checkout_completed_does_not_mark_either_order_when_metadata_and_session_disagree(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        [$otherOrder, $otherPost] = $this->pendingBoostOrder();
        $payload = $this->completedPayload($order, 'evt_cross_order');
        $payload['data']['object']['metadata'] = ['order_id' => (string) $otherOrder->id];
        $payload['data']['object']['client_reference_id'] = (string) $otherOrder->id;
        $logged = $this->captureLogs();

        $this->postStripeWebhook($payload)->assertOk();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $otherOrder->fresh()->status);
        $this->assertFalse($post->fresh()->hasActiveBoost());
        $this->assertFalse($otherPost->fresh()->hasActiveBoost());
        $this->assertLoggedReason($logged, 'Stripe webhook skipped', 'session_order_mismatch', null);
    }

    public function test_checkout_completed_with_unpaid_status_stays_pending(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $payload = $this->completedPayload($order, 'evt_unpaid');
        $payload['data']['object']['payment_status'] = 'unpaid';
        $logged = $this->captureLogs();

        $this->postStripeWebhook($payload)->assertOk();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertFalse($post->fresh()->hasActiveBoost());
        $this->assertLoggedReason($logged, 'Stripe webhook skipped', 'payment_not_confirmed', $order->id);
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

    public function test_a_declined_card_stays_pending_until_stripe_confirms_a_later_payment(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $gateway = $this->fakeStripe();
        $sessionId = $order->payments()->first()->provider_reference;

        $this->postStripeWebhook($this->declinedPayload($order, 'evt_decline_1', 'pi_decline_1', 'generic_decline', 'Your card was declined.'))
            ->assertOk();

        $payment = $order->payments()->first();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(PostBoostStatus::Pending, PostBoost::query()->first()->status);
        $this->assertFalse($post->fresh()->hasActiveBoost());
        $this->assertSame([], $gateway->expiredSessions);
        $this->assertSame('evt_decline_1', $payment->fresh()->payload['declined_attempts'][0]['event_id']);
        $this->assertSame('pi_decline_1', $payment->fresh()->payload['declined_attempts'][0]['payment_intent']);
        $this->assertSame('card_declined', $payment->fresh()->payload['declined_attempts'][0]['code']);
        $this->assertSame('generic_decline', $payment->fresh()->payload['declined_attempts'][0]['decline_code']);
        $this->assertSame('Your card was declined.', $payment->fresh()->payload['declined_attempts'][0]['message']);

        $this->postStripeWebhook($this->declinedPayload($order, 'evt_decline_1', 'pi_decline_1', 'generic_decline', 'Your card was declined.'))
            ->assertOk();

        $this->assertCount(1, $order->payments()->first()->payload['declined_attempts']);

        $this->postStripeWebhook($this->declinedPayload($order, 'evt_decline_2', 'pi_decline_2', 'insufficient_funds', 'Your card has insufficient funds.'))
            ->assertOk();

        $this->actingAs($order->user)
            ->from(route('posts.boost.create', $post))
            ->post(route('posts.boost.store', $post), ['package_id' => $order->package_id])
            ->assertRedirect(route('posts.boost.create', $post))
            ->assertSessionHas('error');

        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, $order->payments()->count());
        $this->assertCount(2, $order->payments()->first()->payload['declined_attempts']);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);

        $gateway->sessions[$sessionId] = $this->settledSession($order, $sessionId, 'pi_success');

        $this->postStripeWebhook($this->completedPayload($order, 'evt_paid_after_decline'))
            ->assertOk();
        $this->postStripeWebhook($this->completedPayload($order, 'evt_paid_after_decline'))
            ->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->payments()->first()->status);
        $this->assertSame(PostBoostStatus::Active, PostBoost::query()->first()->status);
        $this->assertTrue($post->fresh()->hasActiveBoost());
        $this->assertSame(1, PostBoost::query()->count());
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, $order->payments()->count());
        $this->assertSame([], $gateway->expiredSessions);
        $this->assertSame('pi_success', $order->payments()->first()->payload['payment_intent']);
    }

    public function test_async_payment_succeeded_after_a_decline_marks_the_order_paid(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $sessionId = $order->payments()->first()->provider_reference;
        $gateway = $this->fakeStripe();

        $this->postStripeWebhook($this->declinedPayload($order, 'evt_decline_async', 'pi_decline_async', 'generic_decline', 'Your card was declined.'))
            ->assertOk();

        $gateway->sessions[$sessionId] = $this->settledSession($order, $sessionId, 'pi_async_success');
        $payload = $this->completedPayload($order, 'evt_async_paid');
        $payload['type'] = 'checkout.session.async_payment_succeeded';

        $this->postStripeWebhook($payload)->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertTrue($post->fresh()->hasActiveBoost());
        $this->assertSame(1, $order->payments()->count());
    }

    public function test_late_failure_events_do_not_downgrade_a_paid_order_or_its_benefit(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $sessionId = $order->payments()->first()->provider_reference;

        $this->postStripeWebhook($this->completedPayload($order, 'evt_paid_first'))
            ->assertOk();

        $this->postStripeWebhook($this->declinedPayload($order, 'evt_decline_late', 'pi_late', 'generic_decline', 'Your card was declined.'))
            ->assertOk();
        $this->postStripeWebhook($this->expiredPayload($order, $sessionId, 'evt_expired_late'))
            ->assertOk();
        $this->postStripeWebhook($this->asyncFailedPayload($order, $sessionId, 'evt_async_failed_late'))
            ->assertOk();
        $this->postStripeWebhook($this->completedPayload($order, 'evt_paid_first'))
            ->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->payments()->first()->status);
        $this->assertSame(PostBoostStatus::Active, PostBoost::query()->first()->status);
        $this->assertTrue($post->fresh()->hasActiveBoost());
        $this->assertSame(1, PostBoost::query()->currentlyActive()->count());
    }

    public function test_checkout_completed_does_not_pay_when_stripe_says_the_session_is_unpaid(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $gateway = $this->fakeStripe();
        $sessionId = $order->payments()->first()->provider_reference;
        $gateway->sessions[$sessionId] = [
            'id' => $sessionId,
            'status' => 'open',
            'url' => null,
            'payment_status' => 'unpaid',
            'order_id' => (string) $order->id,
            'client_reference_id' => (string) $order->id,
            'payment_intent' => 'pi_open',
            'payment_intent_status' => 'requires_payment_method',
        ];

        $this->postStripeWebhook($this->completedPayload($order, 'evt_claims_paid'))
            ->assertOk();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Pending, $order->payments()->first()->status);
        $this->assertSame(PostBoostStatus::Pending, PostBoost::query()->first()->status);
        $this->assertFalse($post->fresh()->hasActiveBoost());
    }

    public function test_checkout_completed_does_not_pay_when_the_payment_intent_has_not_succeeded(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $gateway = $this->fakeStripe();
        $sessionId = $order->payments()->first()->provider_reference;
        $session = $this->settledSession($order, $sessionId, 'pi_processing');
        $session['payment_intent_status'] = 'processing';
        $gateway->sessions[$sessionId] = $session;

        $this->postStripeWebhook($this->completedPayload($order, 'evt_processing'))
            ->assertOk();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertFalse($post->fresh()->hasActiveBoost());
    }

    public function test_an_expired_webhook_pays_when_stripe_has_already_settled_the_session(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $gateway = $this->fakeStripe();
        $sessionId = $order->payments()->first()->provider_reference;
        $gateway->sessions[$sessionId] = $this->settledSession($order, $sessionId, 'pi_already_settled');

        $this->postStripeWebhook($this->expiredPayload($order, $sessionId, 'evt_expired_but_paid'))
            ->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->payments()->first()->status);
        $this->assertSame(PostBoostStatus::Active, PostBoost::query()->first()->status);
        $this->assertTrue($post->fresh()->hasActiveBoost());
        $this->assertSame([], $gateway->expiredSessions);
    }

    public function test_async_payment_failed_still_cancels_a_pending_unpaid_order(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        $sessionId = $order->payments()->first()->provider_reference;

        $this->postStripeWebhook($this->asyncFailedPayload($order, $sessionId, 'evt_async_failed'))
            ->assertOk();

        $this->assertSame(OrderStatus::Failed, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Failed, $order->payments()->first()->status);
        $this->assertSame(PostBoostStatus::Cancelled, PostBoost::query()->first()->status);
        $this->assertFalse($post->fresh()->hasActiveBoost());
    }

    public function test_a_decline_for_a_different_order_does_not_change_this_order(): void
    {
        [$order, $post] = $this->pendingBoostOrder();
        [$otherOrder] = $this->pendingBoostOrder();
        $order->payments()->first()->forceFill([
            'payload' => ['payment_intent' => 'pi_cross'],
        ])->save();

        $payload = $this->declinedPayload($order, 'evt_cross_decline', 'pi_cross', 'generic_decline', 'Your card was declined.');
        $payload['data']['object']['metadata'] = ['order_id' => (string) $otherOrder->id];

        $this->postStripeWebhook($payload)->assertOk();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $otherOrder->fresh()->status);
        $this->assertSame(['payment_intent' => 'pi_cross'], $order->payments()->first()->payload);
        $this->assertNull($otherOrder->payments()->first()->payload);
        $this->assertFalse($post->fresh()->hasActiveBoost());
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

        $order = Order::query()->latest('id')->firstOrFail();
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
    /**
     * @return array<string, mixed>
     */
    private function declinedPayload(Order $order, string $eventId, string $paymentIntentId, string $declineCode, string $message): array
    {
        return [
            'id' => $eventId,
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => $paymentIntentId,
                    'object' => 'payment_intent',
                    'metadata' => ['order_id' => (string) $order->id],
                    'last_payment_error' => [
                        'code' => 'card_declined',
                        'decline_code' => $declineCode,
                        'message' => $message,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expiredPayload(Order $order, string $sessionId, string $eventId): array
    {
        return [
            'id' => $eventId,
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'object' => 'checkout.session',
                    'payment_status' => 'unpaid',
                    'client_reference_id' => (string) $order->id,
                    'metadata' => ['order_id' => (string) $order->id],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function asyncFailedPayload(Order $order, string $sessionId, string $eventId): array
    {
        return [
            'id' => $eventId,
            'type' => 'checkout.session.async_payment_failed',
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'object' => 'checkout.session',
                    'payment_status' => 'unpaid',
                    'client_reference_id' => (string) $order->id,
                    'metadata' => ['order_id' => (string) $order->id],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settledSession(Order $order, string $sessionId, string $paymentIntentId): array
    {
        return [
            'id' => $sessionId,
            'status' => 'complete',
            'url' => null,
            'payment_status' => 'paid',
            'order_id' => (string) $order->id,
            'client_reference_id' => (string) $order->id,
            'payment_intent' => $paymentIntentId,
            'payment_intent_status' => 'succeeded',
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
        $this->rememberWebhookSession($payload);

        $body = json_encode($payload);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test_123');

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $body);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function rememberWebhookSession(array $payload): void
    {
        $gateway = $this->app->make(StripeCheckoutGateway::class);

        if (! $gateway instanceof FakeStripeCheckoutGateway) {
            $gateway = new FakeStripeCheckoutGateway;
            $this->app->instance(StripeCheckoutGateway::class, $gateway);
        }

        $object = $payload['data']['object'] ?? null;

        if (! is_array($object) || ($object['object'] ?? null) !== 'checkout.session') {
            return;
        }

        $sessionId = $object['id'] ?? null;

        if (! is_string($sessionId) || $sessionId === '' || isset($gateway->sessions[$sessionId])) {
            return;
        }

        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
        $orderId = $metadata['order_id'] ?? null;
        $clientReferenceId = $object['client_reference_id'] ?? null;
        $paymentStatus = (string) ($object['payment_status'] ?? 'unpaid');
        $paymentIntent = $object['payment_intent'] ?? null;

        if (is_array($paymentIntent)) {
            $paymentIntent = $paymentIntent['id'] ?? null;
        }

        $gateway->sessions[$sessionId] = [
            'id' => $sessionId,
            'status' => ($payload['type'] ?? '') === 'checkout.session.expired' ? 'expired' : 'complete',
            'url' => null,
            'payment_status' => $paymentStatus,
            'order_id' => is_scalar($orderId) && (string) $orderId !== '' ? (string) $orderId : null,
            'client_reference_id' => is_scalar($clientReferenceId) && (string) $clientReferenceId !== '' ? (string) $clientReferenceId : null,
            'payment_intent' => is_string($paymentIntent) && $paymentIntent !== '' ? $paymentIntent : null,
            'payment_intent_status' => $paymentStatus === 'paid' ? 'succeeded' : null,
        ];
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

    /**
     * @return ArrayObject<int, MessageLogged>
     */
    private function captureLogs(): ArrayObject
    {
        $logged = new ArrayObject;

        Log::listen(function (MessageLogged $event) use ($logged): void {
            $logged->append($event);
        });

        return $logged;
    }

    /**
     * @param  iterable<int, MessageLogged>  $logged
     */
    private function assertLoggedReason(iterable $logged, string $message, string $reason, ?int $orderId): void
    {
        $found = false;

        foreach ($logged as $event) {
            if ($event->message === $message
                && ($event->context['reason'] ?? null) === $reason
                && ($event->context['order_id'] ?? null) === $orderId) {
                $found = true;
            }
        }

        $this->assertTrue($found, $message.' with reason '.$reason.' was not logged.');
    }

    private function makeEligible(): void
    {
        MonetizationSetting::query()->where('key', 'eligibility_min_followers')->update(['value' => '0']);
        MonetizationSetting::query()->where('key', 'eligibility_min_posts')->update(['value' => '0']);
        MonetizationSetting::query()->where('key', 'eligibility_min_published_posts')->update(['value' => '0']);
        MonetizationSetting::query()->where('key', 'eligibility_min_qualified_views_30d')->update(['value' => '0']);
    }
}
