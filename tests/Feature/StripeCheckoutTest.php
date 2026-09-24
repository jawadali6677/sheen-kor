<?php

namespace Tests\Feature;

use App\Contracts\StripeCheckoutGateway;
use App\Enums\ListingPromotionStatus;
use App\Enums\MonetizationPackageType;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\ListingPromotion;
use App\Models\MarketListing;
use App\Models\MonetizationPackage;
use App\Models\Order;
use App\Models\Post;
use App\Models\User;
use ArrayObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
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
        $this->assertSame(
            route('orders.show', $order).'?checkout=success&session_id={CHECKOUT_SESSION_ID}',
            $gateway->charges[0]['session_options']['success_url'],
        );
        $this->assertSame((string) $order->id, $gateway->charges[0]['session_options']['client_reference_id']);
        $this->assertSame((string) $order->id, $gateway->charges[0]['session_options']['metadata']['order_id']);
        $this->assertSame((string) $order->id, $gateway->charges[0]['session_options']['payment_intent_data']['metadata']['order_id']);
    }

    public function test_success_url_with_a_paid_session_marks_the_listing_promotion_active(): void
    {
        $gateway = $this->fakeStripe();
        [$user, $listing, $order] = $this->pendingListingPromotion();
        $gateway->sessions['cs_test_123'] = $this->paidSession($order, 'cs_test_123');
        $logged = $this->captureLogs();

        $this->actingAs($user)
            ->get(route('orders.show', $order).'?checkout=success&session_id=cs_test_123')
            ->assertOk()
            ->assertSee('This order is paid.')
            ->assertDontSee('Pay with Stripe');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Paid, $order->payments()->first()->status);
        $this->assertTrue($listing->fresh()->hasActivePromotion());
        $this->assertSame(ListingPromotionStatus::Active, ListingPromotion::query()->first()->status);
        $this->assertSame(1, ListingPromotion::query()->currentlyActive()->count());
        $this->assertLoggedReason($logged, 'Stripe checkout return fulfilled', 'fulfilled', $order->id);

        $this->actingAs($user)
            ->get(route('orders.show', $order).'?checkout=success&session_id=cs_test_123')
            ->assertOk()
            ->assertSee('This order is paid.');

        $this->assertSame(1, $gateway->retrieveCount);
        $this->assertSame(1, ListingPromotion::query()->currentlyActive()->count());
        $this->assertLoggedReason($logged, 'Stripe checkout return skipped', 'already_paid', $order->id);
    }

    public function test_success_url_accepts_no_payment_required_sessions(): void
    {
        $gateway = $this->fakeStripe();
        [$user, $listing, $order] = $this->pendingListingPromotion();
        $gateway->sessions['cs_test_123'] = $this->paidSession($order, 'cs_test_123', 'no_payment_required');

        $this->actingAs($user)
            ->get(route('orders.show', $order).'?checkout=success&session_id=cs_test_123')
            ->assertOk()
            ->assertSee('This order is paid.');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertTrue($listing->fresh()->hasActivePromotion());
    }

    public function test_success_url_fulfills_a_session_linked_only_by_the_stored_session_id(): void
    {
        $gateway = $this->fakeStripe();
        [$user, $listing, $order] = $this->pendingListingPromotion();
        $gateway->sessions['cs_test_123'] = [
            'id' => 'cs_test_123',
            'payment_status' => 'paid',
            'order_id' => null,
            'client_reference_id' => null,
            'payment_intent' => 'pi_cs_test_123',
        ];

        $this->actingAs($user)
            ->get(route('orders.show', $order).'?checkout=success&session_id=cs_test_123')
            ->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertTrue($listing->fresh()->hasActivePromotion());
    }

    public function test_success_url_does_not_mark_an_order_when_the_session_belongs_to_another_order(): void
    {
        $gateway = $this->fakeStripe();
        [$user, $listing, $order] = $this->pendingListingPromotion();
        $otherListing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = MonetizationPackage::query()->where('slug', 'listing_featured_7d')->firstOrFail();
        $gateway->nextSessionId = 'cs_test_other';

        $this->actingAs($user)
            ->post(route('market.promote.store', $otherListing), ['package_id' => $package->id]);

        $otherOrder = Order::query()->latest('id')->firstOrFail();
        $gateway->sessions['cs_test_other'] = $this->paidSession($otherOrder, 'cs_test_other');
        $logged = $this->captureLogs();

        $this->actingAs($user)
            ->get(route('orders.show', $order).'?checkout=success&session_id=cs_test_other')
            ->assertOk()
            ->assertSee('That Checkout session belongs to a different order')
            ->assertSee('Pay with Stripe');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertSame(OrderStatus::Pending, $otherOrder->fresh()->status);
        $this->assertFalse($listing->fresh()->hasActivePromotion());
        $this->assertFalse($otherListing->fresh()->hasActivePromotion());
        $this->assertLoggedReason($logged, 'Stripe checkout return skipped', 'session_order_mismatch', $order->id);
    }

    public function test_success_url_with_an_unpaid_session_leaves_the_order_pending(): void
    {
        $gateway = $this->fakeStripe();
        [$user, $listing, $order] = $this->pendingListingPromotion();
        $gateway->sessions['cs_test_123'] = $this->paidSession($order, 'cs_test_123', 'unpaid');
        $logged = $this->captureLogs();

        $this->actingAs($user)
            ->get(route('orders.show', $order).'?checkout=success&session_id=cs_test_123')
            ->assertOk()
            ->assertSee('This checkout is not paid yet')
            ->assertSee('Pay with Stripe');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertFalse($listing->fresh()->hasActivePromotion());
        $this->assertLoggedReason($logged, 'Stripe checkout return skipped', 'payment_not_confirmed', $order->id);
    }

    public function test_success_url_without_a_session_id_stays_pending_until_the_webhook(): void
    {
        $gateway = $this->fakeStripe();
        [$user, $listing, $order] = $this->pendingListingPromotion();
        $logged = $this->captureLogs();

        $this->actingAs($user)
            ->get(route('orders.show', $order).'?checkout=success')
            ->assertOk()
            ->assertSee('marked paid when the webhook arrives')
            ->assertDontSee('Pay with Stripe');

        $this->assertSame(0, $gateway->retrieveCount);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertFalse($listing->fresh()->hasActivePromotion());
        $this->assertLoggedReason($logged, 'Stripe checkout return skipped', 'missing_session_id', $order->id);
    }

    public function test_success_url_stays_pending_when_stripe_cannot_retrieve_the_session(): void
    {
        $gateway = $this->fakeStripe();
        [$user, $listing, $order] = $this->pendingListingPromotion();
        $logged = $this->captureLogs();

        $this->actingAs($user)
            ->get(route('orders.show', $order).'?checkout=success&session_id=cs_test_123')
            ->assertOk()
            ->assertSee('marked paid when the webhook arrives')
            ->assertDontSee('Pay with Stripe');

        $this->assertSame(1, $gateway->retrieveCount);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertFalse($listing->fresh()->hasActivePromotion());
        $this->assertLoggedReason($logged, 'Stripe checkout return skipped', 'session_not_retrieved', $order->id);
    }

    public function test_success_url_ignores_a_session_that_is_not_linked_to_the_order(): void
    {
        $gateway = $this->fakeStripe();
        [$user, $listing, $order] = $this->pendingListingPromotion();
        $gateway->sessions['cs_test_other'] = [
            'id' => 'cs_test_other',
            'payment_status' => 'paid',
            'order_id' => null,
            'client_reference_id' => null,
            'payment_intent' => 'pi_other',
        ];
        $logged = $this->captureLogs();

        $this->actingAs($user)
            ->get(route('orders.show', $order).'?checkout=success&session_id=cs_test_other')
            ->assertOk()
            ->assertSee('not linked to this order')
            ->assertSee('Pay with Stripe');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertFalse($listing->fresh()->hasActivePromotion());
        $this->assertLoggedReason($logged, 'Stripe checkout return skipped', 'session_not_linked', $order->id);
    }

    public function test_success_url_does_not_revive_a_cancelled_order(): void
    {
        $gateway = $this->fakeStripe();
        [$user, $listing, $order] = $this->pendingListingPromotion();
        $gateway->sessions['cs_test_123'] = $this->paidSession($order, 'cs_test_123');

        $this->actingAs($user)
            ->delete(route('orders.destroy', $order));

        $this->actingAs($user)
            ->get(route('orders.show', $order).'?checkout=success&session_id=cs_test_123')
            ->assertOk();

        $this->assertSame(0, $gateway->retrieveCount);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(ListingPromotionStatus::Cancelled, ListingPromotion::query()->first()->status);
        $this->assertFalse($listing->fresh()->hasActivePromotion());
    }

    public function test_webhook_after_success_url_fulfillment_does_not_activate_twice(): void
    {
        $gateway = $this->fakeStripe();
        [$user, $listing, $order] = $this->pendingListingPromotion();
        $gateway->sessions['cs_test_123'] = $this->paidSession($order, 'cs_test_123');

        $this->actingAs($user)
            ->get(route('orders.show', $order).'?checkout=success&session_id=cs_test_123')
            ->assertOk();

        $logged = $this->captureLogs();

        $this->postStripeWebhook($this->completedPayload($order->fresh(), 'evt_after_success'))
            ->assertOk();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(1, ListingPromotion::query()->currentlyActive()->count());
        $this->assertTrue($listing->fresh()->hasActivePromotion());
        $this->assertLoggedReason($logged, 'Stripe webhook skipped', 'order_already_paid', $order->id);
    }

    public function test_another_member_cannot_fulfill_an_order_from_the_success_url(): void
    {
        $gateway = $this->fakeStripe();
        [$user, $listing, $order] = $this->pendingListingPromotion();
        $gateway->sessions['cs_test_123'] = $this->paidSession($order, 'cs_test_123');
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('orders.show', $order).'?checkout=success&session_id=cs_test_123')
            ->assertForbidden();

        $this->assertSame(0, $gateway->retrieveCount);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertFalse($listing->fresh()->hasActivePromotion());
    }

    public function test_guests_cannot_fulfill_an_order_from_the_success_url(): void
    {
        $gateway = $this->fakeStripe();
        [, $listing, $order] = $this->pendingListingPromotion();
        $gateway->sessions['cs_test_123'] = $this->paidSession($order, 'cs_test_123');

        auth()->logout();

        $this->get(route('orders.show', $order).'?checkout=success&session_id=cs_test_123')
            ->assertRedirect(route('login'));

        $this->assertSame(0, $gateway->retrieveCount);
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertFalse($listing->fresh()->hasActivePromotion());
    }

    public function test_admin_order_page_shows_the_stripe_checkout_session_id(): void
    {
        $this->fakeStripe();
        $admin = User::factory()->admin()->create();
        [, , $order] = $this->pendingListingPromotion();

        $this->actingAs($admin)
            ->get(route('admin.monetization.orders.show', $order))
            ->assertOk()
            ->assertSee('Stripe session')
            ->assertSee('cs_test_123');
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

    /**
     * @return array{0: User, 1: MarketListing, 2: Order}
     */
    private function pendingListingPromotion(): array
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');
        $package->forceFill(['price' => '9.00'])->save();

        $this->actingAs($user)
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id]);

        return [$user, $listing, Order::query()->latest('id')->firstOrFail()];
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

    /**
     * @return array<string, mixed>
     */
    private function paidSession(Order $order, string $sessionId, string $paymentStatus = 'paid'): array
    {
        return [
            'id' => $sessionId,
            'payment_status' => $paymentStatus,
            'order_id' => (string) $order->id,
            'client_reference_id' => (string) $order->id,
            'payment_intent' => 'pi_'.$sessionId,
        ];
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
    private function assertLoggedReason(iterable $logged, string $message, string $reason, int $orderId): void
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
        $body = json_encode($payload);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test_123');

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $body);
    }
}
