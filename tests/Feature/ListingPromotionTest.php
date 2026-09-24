<?php

namespace Tests\Feature;

use App\Contracts\StripeCheckoutGateway;
use App\Enums\ListingPromotionPlacement;
use App\Enums\ListingPromotionSource;
use App\Enums\ListingPromotionStatus;
use App\Enums\MarketListingStatus;
use App\Enums\MonetizationPackageType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\ListingPromotion;
use App\Models\MarketCategory;
use App\Models\MarketListing;
use App\Models\MonetizationPackage;
use App\Models\MonetizationSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Support\FakeStripeCheckoutGateway;
use Tests\TestCase;

class ListingPromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_can_see_enabled_promotion_packages_for_a_published_listing(): void
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $this->enablePromotionPackages();

        $this->actingAs($user)
            ->get(route('market.promote.create', $listing))
            ->assertOk()
            ->assertSee('Featured Listing - 7 Days')
            ->assertSee('Top of Category - 7 Days')
            ->assertSee('Continue to payment');
    }

    public function test_pending_promotion_page_explains_the_reservation_is_not_paid(): void
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');

        $this->actingAs($user)
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id]);

        $this->actingAs($user)
            ->get(route('market.promote.create', $listing))
            ->assertOk()
            ->assertSee('Status: Pending payment')
            ->assertSee('An admin can mark the order paid for testing until Stripe Checkout is connected')
            ->assertSee('Cancel pending promotion')
            ->assertDontSee('Continue payment')
            ->assertDontSee('Pay with Stripe')
            ->assertDontSee('Promoted until');

        $this->actingAs($user)
            ->get(route('market.show', $listing))
            ->assertOk()
            ->assertSee('Pending payment')
            ->assertDontSee('Promoted until');

        $this->actingAs($user)
            ->get(route('market.mine'))
            ->assertOk()
            ->assertSee('Pending payment')
            ->assertDontSee('Promoted until');
    }

    public function test_disabled_packages_cannot_be_selected(): void
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = MonetizationPackage::query()->where('slug', 'listing_featured_7d')->firstOrFail();

        $this->actingAs($user)
            ->from(route('market.promote.create', $listing))
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id])
            ->assertSessionHasErrors('package_id');

        $this->assertDatabaseCount('listing_promotions', 0);
    }

    public function test_members_cannot_promote_another_users_listing(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $owner->id]);
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');

        $this->actingAs($other)
            ->get(route('market.promote.create', $listing))
            ->assertForbidden();

        $this->actingAs($other)
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id])
            ->assertForbidden();

        $this->assertDatabaseCount('listing_promotions', 0);
    }

    public function test_unpublished_listings_cannot_be_promoted(): void
    {
        $user = User::factory()->create();
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');

        foreach ([MarketListingStatus::Pending, MarketListingStatus::Rejected] as $status) {
            $listing = MarketListing::factory()->create([
                'user_id' => $user->id,
                'status' => $status,
                'published_at' => null,
            ]);

            $this->actingAs($user)
                ->post(route('market.promote.store', $listing), ['package_id' => $package->id])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('listing_promotions', 0);
    }

    public function test_confirming_a_promotion_creates_a_pending_record_using_the_package_snapshot(): void
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');
        $package->forceFill(['price' => '6.50'])->save();

        $this->actingAs($user)
            ->from(route('market.promote.create', $listing))
            ->post(route('market.promote.store', $listing), [
                'package_id' => $package->id,
                'price' => '99.00',
                'duration_days' => 90,
                'status' => ListingPromotionStatus::Active->value,
                'placement' => ListingPromotionPlacement::BoostRank->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('listing_promotions', [
            'user_id' => $user->id,
            'market_listing_id' => $listing->id,
            'package_id' => $package->id,
            'status' => ListingPromotionStatus::Pending->value,
            'source' => ListingPromotionSource::Request->value,
            'package_slug' => 'listing_featured_7d',
            'placement' => ListingPromotionPlacement::FeaturedHome->value,
            'price' => '6.50',
            'currency' => 'USD',
            'duration_days' => 7,
        ]);

        $this->assertFalse($listing->fresh()->hasActivePromotion());
    }

    public function test_price_duration_and_placement_snapshots_stay_unchanged_after_package_edits(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');
        $package->forceFill(['price' => '6.50', 'duration_days' => 7])->save();

        $this->actingAs($user)
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id]);

        $package->forceFill([
            'price' => '99.00',
            'duration_days' => 30,
            'placement' => ListingPromotionPlacement::BoostRank->value,
        ])->save();

        $promotion = ListingPromotion::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.monetization.promotions.activate', $promotion))
            ->assertRedirect(route('admin.monetization.promotions.index'));

        $promotion->refresh();

        $this->assertTrue($promotion->isCurrentlyActive());
        $this->assertSame('6.50', $promotion->price);
        $this->assertSame(7, $promotion->duration_days);
        $this->assertSame(ListingPromotionPlacement::FeaturedHome, $promotion->placement);
        $this->assertSame('99.00', $package->fresh()->price);
        $this->assertSame(7, (int) $promotion->starts_at->diffInDays($promotion->ends_at));
        $this->assertTrue($listing->fresh()->hasActivePromotion());
    }

    public function test_overlapping_pending_or_active_promotions_are_rejected(): void
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');

        $this->actingAs($user)
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id])
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->from(route('market.promote.create', $listing))
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id])
            ->assertSessionHas('error');

        $this->assertSame(1, ListingPromotion::query()->where('market_listing_id', $listing->id)->count());
    }

    public function test_expired_promotions_are_not_treated_as_active_and_can_be_replaced(): void
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');

        ListingPromotion::factory()->active()->create([
            'user_id' => $user->id,
            'market_listing_id' => $listing->id,
            'package_id' => $package->id,
            'status' => ListingPromotionStatus::Active,
            'starts_at' => now()->subDays(8),
            'ends_at' => now()->subDay(),
            'duration_days' => 7,
        ]);

        $this->assertFalse($listing->fresh()->hasActivePromotion());
        $this->assertSame(ListingPromotionStatus::Expired, ListingPromotion::query()->first()->displayStatus());

        $this->actingAs($user)
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id])
            ->assertSessionHas('success');

        $this->assertSame(2, ListingPromotion::query()->where('market_listing_id', $listing->id)->count());
    }

    public function test_owners_can_cancel_a_pending_promotion(): void
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $promotion = ListingPromotion::factory()->create([
            'user_id' => $user->id,
            'market_listing_id' => $listing->id,
        ]);

        $this->actingAs($user)
            ->delete(route('market.promote.destroy', $promotion))
            ->assertRedirect(route('market.show', $listing));

        $this->assertSame(ListingPromotionStatus::Cancelled, $promotion->fresh()->status);
    }

    public function test_cancelling_a_pending_promotion_cancels_the_order_and_payments(): void
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');
        $package->forceFill(['price' => '6.50'])->save();

        $this->actingAs($user)
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id]);

        $order = Order::query()->firstOrFail();
        $promotion = ListingPromotion::query()->firstOrFail();

        $this->assertSame(PaymentStatus::Pending, $order->payments()->first()->status);

        $this->actingAs($user)
            ->delete(route('market.promote.destroy', $promotion))
            ->assertRedirect(route('market.show', $listing));

        $this->assertSame(ListingPromotionStatus::Cancelled, $promotion->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Cancelled, $order->payments()->first()->status);
        $this->assertSame(0, Payment::query()->where('status', PaymentStatus::Pending)->count());
    }

    public function test_deleting_a_listing_cancels_related_pending_payments(): void
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');
        $package->forceFill(['price' => '6.50'])->save();

        $this->actingAs($user)
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('market.destroy', $listing))
            ->assertRedirect(route('market.index'));

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Cancelled, $order->payments()->first()->status);
        $this->assertSame(0, Payment::query()->where('status', PaymentStatus::Pending)->count());
        $this->assertSame(0, ListingPromotion::query()->count());

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Cancelled');
    }

    public function test_members_cannot_cancel_another_users_pending_promotion(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $owner->id]);
        $promotion = ListingPromotion::factory()->create([
            'user_id' => $owner->id,
            'market_listing_id' => $listing->id,
        ]);

        $this->actingAs($other)
            ->delete(route('market.promote.destroy', $promotion))
            ->assertForbidden();

        $this->assertSame(ListingPromotionStatus::Pending, $promotion->fresh()->status);
    }

    public function test_admins_can_list_filter_and_cancel_promotions(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $user->id,
            'title' => 'Cedar compost bin',
        ]);
        $promotion = ListingPromotion::factory()->create([
            'user_id' => $user->id,
            'market_listing_id' => $listing->id,
            'package_name' => 'Featured Listing - 7 Days',
            'price' => '6.50',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.monetization.promotions.index'))
            ->assertOk()
            ->assertSee('Cedar compost bin')
            ->assertSee('6.50');

        $this->actingAs($admin)
            ->get(route('admin.monetization.promotions.show', $promotion))
            ->assertOk()
            ->assertSee('Activate for testing');

        $this->actingAs($admin)
            ->post(route('admin.monetization.promotions.cancel', $promotion))
            ->assertRedirect(route('admin.monetization.promotions.index'));

        $this->assertSame(ListingPromotionStatus::Cancelled, $promotion->fresh()->status);
    }

    public function test_admins_can_grant_a_promotion_when_unpaid_grants_are_enabled(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $package = MonetizationPackage::query()->where('slug', 'listing_category_7d')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.monetization.promotions.grant'), [
                'listing_id' => $listing->id,
                'package_id' => $package->id,
            ])
            ->assertRedirect(route('admin.monetization.promotions.index'));

        $promotion = ListingPromotion::query()->firstOrFail();

        $this->assertTrue($listing->fresh()->hasActivePromotion());
        $this->assertSame(ListingPromotionSource::AdminGrant, $promotion->source);
        $this->assertSame('listing_category_7d', $promotion->package_slug);
        $this->assertSame(ListingPromotionPlacement::TopOfCategory, $promotion->placement);
        $this->assertSame(7, $promotion->duration_days);
        $this->assertSame($admin->id, $promotion->activated_by);
    }

    public function test_unpaid_promotion_grants_are_blocked_when_the_setting_is_disabled(): void
    {
        MonetizationSetting::query()->where('key', 'admin_unpaid_grants_enabled')->update(['value' => '0']);

        $admin = User::factory()->admin()->create();
        $listing = MarketListing::factory()->create();
        $package = MonetizationPackage::query()->where('slug', 'listing_featured_7d')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.monetization.promotions.index'))
            ->post(route('admin.monetization.promotions.grant'), [
                'listing_id' => $listing->id,
                'package_id' => $package->id,
            ])
            ->assertRedirect(route('admin.monetization.promotions.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('listing_promotions', 0);
    }

    public function test_members_cannot_open_the_promotion_admin_queue(): void
    {
        $member = User::factory()->create();
        $promotion = ListingPromotion::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.monetization.promotions.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('admin.monetization.promotions.activate', $promotion))
            ->assertForbidden();
    }

    public function test_published_listings_show_a_featured_label_when_active(): void
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $user->id,
            'title' => 'Visible featured planter',
        ]);

        ListingPromotion::factory()->active()->create([
            'user_id' => $user->id,
            'market_listing_id' => $listing->id,
            'placement' => ListingPromotionPlacement::FeaturedHome,
        ]);

        $this->actingAs($user)
            ->get(route('market.show', $listing))
            ->assertOk()
            ->assertSee('Featured')
            ->assertSee('Promote listing');

        $this->get(route('market.index'))
            ->assertOk()
            ->assertSee('Visible featured planter')
            ->assertSee('Featured');
    }

    public function test_featured_listings_sort_ahead_of_newer_organic_listings(): void
    {
        $organic = MarketListing::factory()->create([
            'title' => 'Newer organic stool',
            'published_at' => now()->subHour(),
        ]);
        $featured = MarketListing::factory()->create([
            'title' => 'Older featured stool',
            'published_at' => now()->subDays(3),
        ]);

        ListingPromotion::factory()->active()->create([
            'user_id' => $featured->user_id,
            'market_listing_id' => $featured->id,
            'placement' => ListingPromotionPlacement::FeaturedHome,
        ]);

        $this->get(route('market.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Older featured stool',
                'Newer organic stool',
            ]);

        $this->assertTrue($organic->fresh()->status === MarketListingStatus::Published);
    }

    public function test_top_of_category_does_not_jump_the_unfiltered_catalog(): void
    {
        $category = MarketCategory::factory()->create();
        $organic = MarketListing::factory()->create([
            'title' => 'Newer organic lamp',
            'market_category_id' => $category->id,
            'published_at' => now()->subHour(),
        ]);
        $topOfCategory = MarketListing::factory()->create([
            'title' => 'Older category lamp',
            'market_category_id' => $category->id,
            'published_at' => now()->subDays(3),
        ]);

        ListingPromotion::factory()->active()->create([
            'user_id' => $topOfCategory->user_id,
            'market_listing_id' => $topOfCategory->id,
            'placement' => ListingPromotionPlacement::TopOfCategory,
        ]);

        $this->get(route('market.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Newer organic lamp',
                'Older category lamp',
            ]);

        $this->get(route('market.index', ['category' => $category->id]))
            ->assertOk()
            ->assertSeeInOrder([
                'Older category lamp',
                'Newer organic lamp',
            ]);

        $this->assertTrue($organic->fresh()->status === MarketListingStatus::Published);
    }

    public function test_nearby_search_keeps_promotions_inside_the_geo_filter(): void
    {
        $nearbyOrganic = MarketListing::factory()->create([
            'title' => 'Nearby organic bench',
            'latitude' => 36.1911,
            'longitude' => 44.0092,
            'published_at' => now()->subHour(),
        ]);
        $nearbyFeatured = MarketListing::factory()->create([
            'title' => 'Nearby featured bench',
            'latitude' => 36.1912,
            'longitude' => 44.0093,
            'published_at' => now()->subDays(4),
        ]);
        $farFeatured = MarketListing::factory()->create([
            'title' => 'Far featured bench',
            'latitude' => 51.5074,
            'longitude' => -0.1278,
        ]);

        ListingPromotion::factory()->active()->create([
            'user_id' => $nearbyFeatured->user_id,
            'market_listing_id' => $nearbyFeatured->id,
            'placement' => ListingPromotionPlacement::FeaturedHome,
        ]);
        ListingPromotion::factory()->active()->create([
            'user_id' => $farFeatured->user_id,
            'market_listing_id' => $farFeatured->id,
            'placement' => ListingPromotionPlacement::FeaturedHome,
        ]);

        $this->get(route('market.index', [
            'near_lat' => 36.1911,
            'near_lng' => 44.0092,
            'radius_km' => 25,
        ]))
            ->assertOk()
            ->assertSeeInOrder([
                'Nearby featured bench',
                'Nearby organic bench',
            ])
            ->assertDontSee('Far featured bench');

        $this->assertTrue($nearbyOrganic->fresh()->status === MarketListingStatus::Published);
    }

    public function test_completing_a_listing_cancels_open_promotions(): void
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $promotion = ListingPromotion::factory()->active()->create([
            'user_id' => $user->id,
            'market_listing_id' => $listing->id,
        ]);

        $this->actingAs($user)
            ->post(route('market.close', $listing))
            ->assertRedirect();

        $this->assertSame(ListingPromotionStatus::Cancelled, $promotion->fresh()->status);
        $this->assertFalse($listing->fresh()->hasActivePromotion());
    }

    public function test_pending_promote_page_shows_continue_payment_when_stripe_is_connected(): void
    {
        $this->fakeStripe();
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $user->id,
            'title' => 'new one',
        ]);
        $package = $this->enablePromotionPackages()->firstWhere('slug', 'listing_featured_7d');
        $package->forceFill(['price' => '1.50'])->save();

        $this->actingAs($user)
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id])
            ->assertRedirect('https://checkout.stripe.test/cs_test_123');

        $order = Order::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('market.promote.create', $listing))
            ->assertOk()
            ->assertSee('new one')
            ->assertSee('Status: Pending payment')
            ->assertSee('Pay with Stripe to finish checkout')
            ->assertSee('Continue payment')
            ->assertSee(route('orders.pay', $order), false)
            ->assertSee('View order')
            ->assertSee(route('orders.show', $order), false)
            ->assertSee('Cancel pending promotion')
            ->assertSeeInOrder([
                'Continue payment',
                'View order',
                'Cancel pending promotion',
            ])
            ->assertDontSee('An admin marks it paid for testing until a payment provider is connected')
            ->assertDontSee('until a payment provider is connected')
            ->assertDontSee('Promoted until');

        $this->actingAs($user)
            ->from(route('market.promote.create', $listing))
            ->post(route('orders.pay', $order))
            ->assertRedirect('https://checkout.stripe.test/cs_test_124');
    }

    public function test_package_selection_does_not_say_only_an_admin_can_mark_paid_when_stripe_is_connected(): void
    {
        $this->fakeStripe();
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $this->enablePromotionPackages();

        $this->actingAs($user)
            ->get(route('market.promote.create', $listing))
            ->assertOk()
            ->assertSee('opens Stripe Checkout')
            ->assertSee('Promote for 7 days · Featured Listing - 7 Days · 0.00 USD')
            ->assertSee('Promote for 7 days · Top of Category - 7 Days · 0.00 USD')
            ->assertSee('Promote for 7 days · Listing Promotion - 7 Days · 0.00 USD')
            ->assertDontSee('Confirm pending promotion')
            ->assertDontSee('An admin marks it paid for testing until Stripe Checkout is connected')
            ->assertDontSee('until a payment provider is connected');
    }

    /**
     * @return Collection<int, MonetizationPackage>
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
}
