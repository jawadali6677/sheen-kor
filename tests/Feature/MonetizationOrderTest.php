<?php

namespace Tests\Feature;

use App\Enums\ListingPromotionStatus;
use App\Enums\MonetizationPackageType;
use App\Enums\OrderStatus;
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
use Tests\TestCase;

class MonetizationOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_snapshots_package_price_and_ignores_client_amount(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');
        $package->forceFill(['price' => '4.50'])->save();

        $this->actingAs($user)
            ->post(route('posts.boost.store', $post), [
                'package_id' => $package->id,
                'amount' => '99.00',
                'price' => '99.00',
            ]);

        $order = Order::query()->firstOrFail();

        $this->assertSame('4.50', $order->amount);
        $this->assertSame('4.50', $order->snapshot['price']);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payments()->first()->status);
        $this->assertFalse($post->fresh()->hasActiveBoost());

        $package->forceFill(['price' => '88.00'])->save();

        $this->assertSame('4.50', $order->fresh()->amount);
    }

    public function test_members_cannot_mark_an_order_paid(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');

        $this->actingAs($user)
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.monetization.orders.mark-paid', $order))
            ->assertForbidden();

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
        $this->assertFalse($post->fresh()->hasActiveBoost());
    }

    public function test_admin_mark_paid_activates_a_boost_and_is_idempotent(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');

        $this->actingAs($user)
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.monetization.orders.mark-paid', $order))
            ->assertRedirect(route('admin.monetization.orders.show', $order));

        $this->assertTrue($post->fresh()->hasActiveBoost());
        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(1, PostBoost::query()->currentlyActive()->count());

        $this->actingAs($admin)
            ->post(route('admin.monetization.orders.mark-paid', $order))
            ->assertRedirect(route('admin.monetization.orders.show', $order));

        $this->assertSame(1, PostBoost::query()->currentlyActive()->count());
        $this->assertSame(1, Order::query()->where('status', OrderStatus::Paid)->count());
    }

    public function test_admin_mark_paid_activates_a_listing_promotion(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');

        $this->actingAs($user)
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id]);

        $order = Order::query()->firstOrFail();
        $this->assertFalse($listing->fresh()->hasActivePromotion());

        $this->actingAs($admin)
            ->post(route('admin.monetization.orders.mark-paid', $order));

        $this->assertTrue($listing->fresh()->hasActivePromotion());
        $this->assertSame(ListingPromotionStatus::Active, ListingPromotion::query()->first()->status);
    }

    public function test_unpaid_green_tick_stays_out_of_the_review_queue(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->makeEligible();
        $package = $this->enableGreenTickPackages()->firstWhere('slug', 'green_tick_monthly');

        $this->actingAs($user)
            ->post(route('green-tick.store'), ['package_id' => $package->id]);

        $verification = UserVerification::query()->firstOrFail();
        $order = Order::query()->firstOrFail();

        $this->assertSame(UserVerificationStatus::PendingPayment, $verification->status);
        Notification::assertNotSentTo($admin, GreenTickNeedsReview::class);

        $this->actingAs($admin)
            ->get(route('admin.monetization.green-ticks.index'))
            ->assertOk()
            ->assertSee('No Green Tick requests in this list.');

        $this->actingAs($admin)
            ->post(route('admin.monetization.orders.mark-paid', $order));

        $this->assertSame(UserVerificationStatus::PendingReview, $verification->fresh()->status);
        Notification::assertSentTo($admin, GreenTickNeedsReview::class);

        $this->actingAs($admin)
            ->get(route('admin.monetization.green-ticks.index'))
            ->assertOk()
            ->assertSee($user->email)
            ->assertDontSee('No Green Tick requests in this list.');
    }

    public function test_green_tick_activates_after_mark_paid_when_review_is_disabled(): void
    {
        MonetizationSetting::query()->where('key', 'green_tick_requires_review')->update(['value' => '0']);

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->makeEligible();
        $package = $this->enableGreenTickPackages()->firstWhere('slug', 'green_tick_monthly');

        $this->actingAs($user)
            ->post(route('green-tick.store'), ['package_id' => $package->id]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.monetization.orders.mark-paid', $order));

        $this->assertTrue($user->fresh()->hasActiveGreenTick());
        $this->assertSame(UserVerificationStatus::Active, UserVerification::query()->first()->status);
    }

    public function test_owners_can_cancel_a_pending_order_and_entitlement(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');

        $this->actingAs($user)
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('orders.destroy', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(PostBoostStatus::Cancelled, PostBoost::query()->first()->status);
        $this->assertSame(PaymentStatus::Cancelled, $order->payments()->first()->status);
    }

    public function test_unpaid_admin_grants_still_create_no_order(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = MonetizationPackage::query()->where('slug', 'post_boost_7d')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.monetization.boosts.grant'), [
                'post_id' => $post->id,
                'package_id' => $package->id,
            ]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertTrue($post->fresh()->hasActiveBoost());
    }

    public function test_admins_can_search_orders_and_members_cannot(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'River Cleanup']);
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');

        $this->actingAs($user)
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id]);

        $this->actingAs($admin)
            ->get(route('admin.monetization.orders.index', ['q' => 'River']))
            ->assertOk()
            ->assertSee('River Cleanup');

        $this->actingAs($user)
            ->get(route('admin.monetization.orders.index'))
            ->assertForbidden();
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
        MonetizationSetting::query()->where('key', 'eligibility_min_published_posts')->update(['value' => '0']);
        MonetizationSetting::query()->where('key', 'eligibility_min_qualified_views_30d')->update(['value' => '0']);
    }
}
