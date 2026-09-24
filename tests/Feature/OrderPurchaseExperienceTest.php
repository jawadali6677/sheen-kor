<?php

namespace Tests\Feature;

use App\Enums\ListingPromotionStatus;
use App\Enums\MarketListingStatus;
use App\Enums\MonetizationPackageType;
use App\Enums\OrderStatus;
use App\Models\MarketListing;
use App\Models\MonetizationPackage;
use App\Models\MonetizationSetting;
use App\Models\Order;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPurchaseExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_listing_promotion_explains_the_benefit_and_exits_to_the_listing(): void
    {
        $this->travelTo('2026-09-24 12:00:00');

        $user = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $user->id,
            'title' => 'Cedar window box',
        ]);
        $order = $this->purchaseListing($user, $listing, 'listing_featured_7d');

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertSee('This order promotes Cedar window box for 7 days after payment.')
            ->assertDontSee('Your listing is featured for 7 days.');

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.monetization.orders.mark-paid', $order));

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertSee('Your listing is featured for 7 days.')
            ->assertSee('Featured until Oct 1, 2026.')
            ->assertSee('Payment received.')
            ->assertSee('View your listing')
            ->assertSee('My Listings')
            ->assertSee('Back to Market')
            ->assertSee('My Orders')
            ->assertSee('Sep 24, 2026')
            ->assertSee('Oct 1, 2026')
            ->assertSee(route('market.show', $listing))
            ->assertSee(route('market.mine'))
            ->assertSee(route('market.index'))
            ->assertSee(route('orders.index'))
            ->assertDontSee('This order is paid.');

        $this->actingAs($user)
            ->get(route('market.show', $listing))
            ->assertSee('Featured')
            ->assertSee('Featured until Oct 1, 2026');

        $this->actingAs($user)
            ->get(route('market.promote.create', $listing))
            ->assertSee('Featured until Oct 1, 2026');

        $this->actingAs($user)
            ->get(route('market.mine'))
            ->assertSee('Cedar window box')
            ->assertSee('Featured until Oct 1, 2026');
    }

    public function test_paid_top_of_category_promotion_names_that_placement(): void
    {
        $this->travelTo('2026-09-24 12:00:00');

        $user = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $user->id,
            'title' => 'Herb drying rack',
        ]);
        $order = $this->purchaseListing($user, $listing, 'listing_category_7d');

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.monetization.orders.mark-paid', $order));

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertSee('Your listing is at the top of its category for 7 days.')
            ->assertSee('Top of Category until Oct 1, 2026.');
    }

    public function test_paid_promoted_listing_uses_the_promoted_label(): void
    {
        $this->travelTo('2026-09-24 12:00:00');

        $user = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $user->id,
            'title' => 'Compost caddy',
        ]);
        $order = $this->purchaseListing($user, $listing, 'listing_boost_7d');

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.monetization.orders.mark-paid', $order));

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertSee('Your listing is promoted for 7 days.')
            ->assertSee('Promoted until Oct 1, 2026.');

        $this->actingAs($user)
            ->get(route('market.show', $listing))
            ->assertSee('Promoted until Oct 1, 2026');
    }

    public function test_expired_listing_promotion_does_not_claim_the_listing_is_still_featured(): void
    {
        $this->travelTo('2026-09-24 12:00:00');

        $user = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $user->id,
            'title' => 'Expired planter',
        ]);
        $order = $this->purchaseListing($user, $listing, 'listing_featured_7d');

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.monetization.orders.mark-paid', $order));

        $order->listingPromotion->forceFill([
            'ends_at' => now()->subMinute(),
        ])->save();

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertSee('Your listing promotion has ended.')
            ->assertDontSee('Your listing is featured');

        $this->actingAs($user)
            ->get(route('market.show', $listing))
            ->assertDontSee('Featured until');
    }

    public function test_paid_post_boost_links_to_the_post_and_shows_when_it_ends(): void
    {
        $this->travelTo('2026-09-24 12:00:00');

        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Balcony compost notes',
        ]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');

        $this->actingAs($user)
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id]);

        $order = Order::query()->where('post_id', $post->id)->firstOrFail();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.monetization.orders.mark-paid', $order));

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertSee('Your post is boosted for 1 day.')
            ->assertSee('Boosted until Sep 25, 2026.')
            ->assertSee('View your post')
            ->assertSee(route('posts.show', $post))
            ->assertSee('My Orders')
            ->assertDontSee('My Listings');

        $this->actingAs($user)
            ->get(route('posts.show', $post))
            ->assertSee('Boosted')
            ->assertSee('Boosted until Sep 25, 2026');

        $this->actingAs($user)
            ->get(route('posts.boost.create', $post))
            ->assertSee('Boosted until Sep 25, 2026');
    }

    public function test_paid_green_tick_explains_review_and_links_to_the_profile(): void
    {
        $this->travelTo('2026-09-24 12:00:00');

        $user = User::factory()->create(['name' => 'Ada Planter']);
        $this->makeEligible();
        $package = $this->enableGreenTickPackages()->firstWhere('slug', 'green_tick_monthly');

        $this->actingAs($user)
            ->post(route('green-tick.store'), ['package_id' => $package->id]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.monetization.orders.mark-paid', $order));

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertSee('Your Green Tick payment is complete. We will review your profile before the badge appears.')
            ->assertSee('View your profile')
            ->assertSee(route('users.show', $user))
            ->assertDontSee('Your Green Tick is active');
    }

    public function test_my_orders_lists_a_members_purchases_and_hides_other_members(): void
    {
        $this->travelTo('2026-09-24 12:00:00');

        $user = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $user->id,
            'title' => 'Visible cedar box',
        ]);
        $order = $this->purchaseListing($user, $listing, 'listing_featured_7d');

        $other = User::factory()->create();
        $hidden = MarketListing::factory()->create([
            'user_id' => $other->id,
            'title' => 'Hidden clay pot',
        ]);
        $this->purchaseListing($other, $hidden, 'listing_featured_7d');

        $this->actingAs($user)
            ->get(route('orders.index'))
            ->assertSee('My Orders')
            ->assertSee('Order #'.$order->id)
            ->assertSee('Listing promotion')
            ->assertSee('Visible cedar box')
            ->assertSee('Featured Listing - 7 Days')
            ->assertSee('Pending')
            ->assertSee('Sep 24, 2026')
            ->assertSee($order->amount.' '.$order->currency)
            ->assertSee(route('orders.show', $order))
            ->assertSee('View order')
            ->assertDontSee('Hidden clay pot');

        $this->actingAs($other)
            ->get(route('orders.show', $order))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertSee(route('orders.index'));
    }

    public function test_paid_listing_promotion_stays_pending_when_the_listing_is_unpublished(): void
    {
        $this->travelTo('2026-09-24 12:00:00');

        $user = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $user->id,
            'title' => 'Unpublished cedar box',
            'description' => 'A cedar box for herbs on the balcony rail.',
        ]);
        $order = $this->purchaseListing($user, $listing, 'listing_featured_7d');

        $listing->forceFill([
            'status' => MarketListingStatus::Pending,
            'published_at' => null,
        ])->save();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.monetization.orders.mark-paid', $order));

        $order->refresh()->load('listingPromotion');

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame(ListingPromotionStatus::Pending, $order->listingPromotion->status);

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertSee('Payment received.')
            ->assertSee('Your listing promotion is pending until the listing is published.')
            ->assertSee('Unpublished cedar box')
            ->assertSee('Featured Listing')
            ->assertSee('7 days')
            ->assertSee('Pending')
            ->assertDontSee('Pending payment')
            ->assertDontSee('Your listing is featured')
            ->assertDontSee('Featured until')
            ->assertDontSee('entitlement')
            ->assertDontSee('fulfillment');

        $this->actingAs($user)
            ->get(route('market.show', $listing))
            ->assertSee('Pending')
            ->assertDontSee('Featured until')
            ->assertDontSee('Pending payment');

        $this->actingAs($user)
            ->get(route('market.mine'))
            ->assertSee('Unpublished cedar box')
            ->assertSee('Pending')
            ->assertDontSee('Featured until')
            ->assertDontSee('Pending payment');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.monetization.orders.show', $order))
            ->assertSee('Payment: Paid')
            ->assertSee('Benefit: Pending')
            ->assertDontSee('Benefit: Active');
    }

    public function test_paid_post_boost_stays_pending_when_the_post_is_unpublished(): void
    {
        $this->travelTo('2026-09-24 12:00:00');

        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Unpublished compost notes',
            'content' => 'Notes about a balcony compost bin.',
        ]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');

        $this->actingAs($user)
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id]);

        $order = Order::query()->where('post_id', $post->id)->firstOrFail();

        $post->forceFill([
            'status' => 'pending',
            'published_at' => null,
        ])->save();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.monetization.orders.mark-paid', $order));

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertSee('Payment received.')
            ->assertSee('Your post boost is pending until the post is published.')
            ->assertSee('Unpublished compost notes')
            ->assertSee('Pending')
            ->assertDontSee('Pending payment')
            ->assertDontSee('Your post is boosted')
            ->assertDontSee('Boosted until');

        $this->actingAs($user)
            ->get(route('posts.show', $post))
            ->assertSee('Pending')
            ->assertDontSee('Boosted until')
            ->assertDontSee('Pending payment');
    }

    public function test_guests_are_redirected_from_my_orders(): void
    {
        $this->get(route('orders.index'))
            ->assertRedirect(route('login'));
    }

    public function test_member_with_no_orders_sees_an_empty_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('orders.index'))
            ->assertSee('You have no orders yet.');
    }

    public function test_order_pages_escape_purchased_item_names(): void
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $user->id,
            'title' => 'Window <script>alert(1)</script> box',
        ]);
        $order = $this->purchaseListing($user, $listing, 'listing_featured_7d');

        $this->actingAs($user)
            ->get(route('orders.show', $order))
            ->assertSee('Window')
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->actingAs($user)
            ->get(route('orders.index'))
            ->assertSee('Window')
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_pending_promotion_page_continues_to_the_order(): void
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create(['user_id' => $user->id]);
        $order = $this->purchaseListing($user, $listing, 'listing_featured_7d');

        $this->actingAs($user)
            ->get(route('market.promote.create', $listing))
            ->assertSee('Continue to payment')
            ->assertSee(route('orders.show', $order))
            ->assertDontSee('Confirm pending promotion');
    }

    public function test_admins_see_payment_benefit_and_the_related_listing(): void
    {
        $this->travelTo('2026-09-24 12:00:00');

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'River Seller']);
        $listing = MarketListing::factory()->create([
            'user_id' => $user->id,
            'title' => 'River market stool',
        ]);
        $order = $this->purchaseListing($user, $listing, 'listing_featured_7d');

        $this->actingAs($admin)
            ->get(route('admin.monetization.orders.index'))
            ->assertSee('River Seller')
            ->assertSee('Listing promotion')
            ->assertSee('River market stool')
            ->assertSee('Pending')
            ->assertSee('Pending payment')
            ->assertSee('Failed')
            ->assertSee('Featured Listing')
            ->assertSee(route('admin.market.show', $listing));

        $this->actingAs($admin)
            ->get(route('admin.monetization.orders.show', $order))
            ->assertSee('Mark paid')
            ->assertSee('Payment: Pending');

        $this->actingAs($admin)
            ->post(route('admin.monetization.orders.mark-paid', $order));

        $this->actingAs($admin)
            ->get(route('admin.monetization.orders.show', $order))
            ->assertSee('Payment: Paid')
            ->assertSee('Benefit: Active')
            ->assertSee('Placement: Featured Listing')
            ->assertSee('Featured until Oct 1, 2026')
            ->assertSee(route('admin.market.show', $listing));

        $this->actingAs($admin)
            ->get(route('admin.market.index', ['status' => 'published']))
            ->assertSee('River market stool')
            ->assertSee('Featured Listing')
            ->assertSee('Active')
            ->assertSee('Featured until Oct 1, 2026');

        $this->actingAs($admin)
            ->get(route('admin.market.show', $listing))
            ->assertSee('Promotion')
            ->assertSee('Featured Listing')
            ->assertSee('Active')
            ->assertSee('Featured until Oct 1, 2026');
    }

    private function purchaseListing(User $user, MarketListing $listing, string $slug): Order
    {
        $package = $this->enablePromotionPackages()->firstWhere('slug', $slug);

        $this->actingAs($user)
            ->post(route('market.promote.store', $listing), ['package_id' => $package->id]);

        return Order::query()->where('market_listing_id', $listing->id)->firstOrFail();
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

    private function enableBoostPackages()
    {
        MonetizationPackage::query()
            ->where('type', MonetizationPackageType::PostBoost)
            ->update(['is_enabled' => true]);

        return MonetizationPackage::query()
            ->where('type', MonetizationPackageType::PostBoost)
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
