<?php

namespace Tests\Feature;

use App\Enums\MonetizationPackageType;
use App\Enums\UserVerificationSource;
use App\Enums\UserVerificationStatus;
use App\Models\MonetizationPackage;
use App\Models\MonetizationSetting;
use App\Models\Order;
use App\Models\Post;
use App\Models\User;
use App\Models\UserVerification;
use App\Notifications\GreenTickNeedsReview;
use App\Notifications\GreenTickReviewResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GreenTickTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_request_a_green_tick_without_meeting_payout_thresholds(): void
    {
        $user = User::factory()->create();
        $package = $this->enableGreenTickPackages()->firstWhere('slug', 'green_tick_monthly');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('green-tick.store'), ['package_id' => $package->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user_verifications', [
            'user_id' => $user->id,
            'status' => UserVerificationStatus::PendingPayment->value,
        ]);

        $payout = $user->fresh()->payoutEligibility();

        $this->assertFalse($payout['eligible']);
        $this->assertSame(0, $payout['green_tick']['current']);
        $this->assertSame(1, $payout['green_tick']['required']);
        $this->assertFalse($payout['green_tick']['met']);
        $this->assertSame(0, $payout['followers']['current']);
        $this->assertSame(500, $payout['followers']['required']);
        $this->assertFalse($payout['followers']['met']);
        $this->assertSame(0, $payout['published_posts']['current']);
        $this->assertSame(10, $payout['published_posts']['required']);
        $this->assertFalse($payout['published_posts']['met']);
        $this->assertFalse($user->fresh()->hasActiveGreenTick());
    }

    public function test_eligible_members_can_request_an_enabled_green_tick_package(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->makeEligible();
        $package = $this->enableGreenTickPackages()->firstWhere('slug', 'green_tick_monthly');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('green-tick.store'), ['package_id' => $package->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user_verifications', [
            'user_id' => $user->id,
            'package_slug' => 'green_tick_monthly',
            'status' => UserVerificationStatus::PendingPayment->value,
            'source' => UserVerificationSource::Request->value,
            'price' => '0.00',
        ]);

        Notification::assertNotSentTo($admin, GreenTickNeedsReview::class);
    }

    public function test_disabled_packages_cannot_be_requested(): void
    {
        $user = User::factory()->create();
        $this->makeEligible();
        $package = MonetizationPackage::query()->where('slug', 'green_tick_yearly')->firstOrFail();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('green-tick.store'), ['package_id' => $package->id])
            ->assertSessionHasErrors('package_id');

        $this->assertDatabaseCount('user_verifications', 0);
    }

    public function test_duplicate_pending_or_active_requests_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->makeEligible();
        $package = $this->enableGreenTickPackages()->firstWhere('slug', 'green_tick_monthly');

        $this->actingAs($user)
            ->post(route('green-tick.store'), ['package_id' => $package->id])
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('green-tick.store'), ['package_id' => $package->id])
            ->assertSessionHas('error');

        $this->assertSame(1, UserVerification::query()->where('user_id', $user->id)->count());
    }

    public function test_members_can_cancel_a_pending_request(): void
    {
        $user = User::factory()->create();
        $verification = UserVerification::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('green-tick.destroy', $verification))
            ->assertRedirect();

        $this->assertSame(UserVerificationStatus::Cancelled, $verification->fresh()->status);
    }

    public function test_admins_can_approve_a_pending_request_using_the_price_snapshot(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $package = MonetizationPackage::query()->where('slug', 'green_tick_monthly')->firstOrFail();
        $package->forceFill(['price' => '9.50', 'is_enabled' => true])->save();

        $verification = UserVerification::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'price' => '9.50',
            'currency' => 'USD',
            'duration_days' => 30,
        ]);

        $package->forceFill(['price' => '99.00'])->save();

        $this->actingAs($admin)
            ->post(route('admin.monetization.green-ticks.approve', $verification))
            ->assertRedirect(route('admin.monetization.green-ticks.index'));

        $verification->refresh();

        $this->assertTrue($verification->isCurrentlyActive());
        $this->assertTrue($user->fresh()->hasActiveGreenTick());
        $this->assertSame('9.50', $verification->price);
        $this->assertSame('99.00', $package->fresh()->price);
        $this->assertNotNull($verification->starts_at);
        $this->assertSame(30, (int) $verification->starts_at->diffInDays($verification->ends_at));
        Notification::assertSentTo($user, GreenTickReviewResult::class);
    }

    public function test_admins_can_reject_a_pending_request(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $verification = UserVerification::factory()->create(['user_id' => $user->id]);

        $this->actingAs($admin)
            ->post(route('admin.monetization.green-ticks.reject', $verification), [
                'review_notes' => 'Not enough community history.',
            ])
            ->assertRedirect(route('admin.monetization.green-ticks.index'));

        $this->assertSame(UserVerificationStatus::Rejected, $verification->fresh()->status);
        $this->assertFalse($user->fresh()->hasActiveGreenTick());
        $this->assertSame('Not enough community history.', $verification->fresh()->review_notes);
    }

    public function test_admins_can_grant_green_tick_when_unpaid_grants_are_enabled(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $package = MonetizationPackage::query()->where('slug', 'green_tick_yearly')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.monetization.green-ticks.grant'), [
                'user_id' => $user->id,
                'package_id' => $package->id,
            ])
            ->assertRedirect(route('admin.monetization.green-ticks.index'));

        $this->assertTrue($user->fresh()->hasActiveGreenTick());
        $this->assertDatabaseHas('user_verifications', [
            'user_id' => $user->id,
            'source' => UserVerificationSource::AdminGrant->value,
            'package_slug' => 'green_tick_yearly',
            'duration_days' => 365,
        ]);
    }

    public function test_unpaid_grants_are_blocked_when_the_setting_is_disabled(): void
    {
        MonetizationSetting::query()->where('key', 'admin_unpaid_grants_enabled')->update(['value' => '0']);

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $package = MonetizationPackage::query()->where('slug', 'green_tick_monthly')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.monetization.green-ticks.index'))
            ->post(route('admin.monetization.green-ticks.grant'), [
                'user_id' => $user->id,
                'package_id' => $package->id,
            ])
            ->assertRedirect(route('admin.monetization.green-ticks.index'))
            ->assertSessionHas('error');

        $this->assertFalse($user->fresh()->hasActiveGreenTick());
    }

    public function test_expired_green_ticks_are_not_treated_as_active(): void
    {
        $user = User::factory()->create();
        UserVerification::factory()->active()->create([
            'user_id' => $user->id,
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subDay(),
        ]);

        $this->assertFalse($user->fresh()->hasActiveGreenTick());
        $this->assertSame(UserVerificationStatus::Expired, $user->currentGreenTickVerification()?->displayStatus());
    }

    public function test_members_cannot_open_the_green_tick_admin_queue(): void
    {
        $member = User::factory()->create();
        $verification = UserVerification::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.monetization.green-ticks.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('admin.monetization.green-ticks.approve', $verification))
            ->assertForbidden();
    }

    public function test_profile_edit_shows_green_tick_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Green Tick')
            ->assertSee('Payout eligibility')
            ->assertSee('Buying a Green Tick does not grant payout access.')
            ->assertSee('0 / 500 followers')
            ->assertSee('0 / 10 published posts')
            ->assertSee('Not yet')
            ->assertSee('No Green Tick packages are enabled.')
            ->assertDontSee('qualified views')
            ->assertDontSee('You are not eligible to request a Green Tick yet');
    }

    public function test_profile_offers_green_tick_when_follower_and_post_minimums_are_met_and_view_minimum_is_zero(): void
    {
        $user = User::factory()->create();
        $this->meetFollowerAndPostMinimums($user);
        $this->enableGreenTickPackages();

        $this->assertSame(0, monetization_setting('eligibility_min_qualified_views_30d', 1));

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('1 / 1 followers')
            ->assertSee('1 / 1 published posts')
            ->assertSee('Met')
            ->assertSee('Not yet')
            ->assertSee('Get Green Tick')
            ->assertDontSee('qualified views')
            ->assertDontSee('You are not eligible to request a Green Tick yet.');
    }

    public function test_member_who_meets_follower_and_post_minimums_can_buy_when_view_minimum_is_zero(): void
    {
        $user = User::factory()->create();
        $this->meetFollowerAndPostMinimums($user);
        $package = $this->enableGreenTickPackages()->firstWhere('slug', 'green_tick_monthly');

        $this->assertSame(0, monetization_setting('eligibility_min_qualified_views_30d', 1));

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('green-tick.store'), ['package_id' => $package->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user_verifications', [
            'user_id' => $user->id,
            'package_slug' => 'green_tick_monthly',
            'status' => UserVerificationStatus::PendingPayment->value,
            'source' => UserVerificationSource::Request->value,
        ]);
    }

    public function test_follower_minimum_does_not_block_a_green_tick_purchase(): void
    {
        MonetizationSetting::query()->where('key', 'eligibility_min_followers')->update(['value' => '1']);
        MonetizationSetting::query()->where('key', 'eligibility_min_published_posts')->update(['value' => '1']);

        $user = User::factory()->create();
        Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
        ]);
        $package = $this->enableGreenTickPackages()->firstWhere('slug', 'green_tick_monthly');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('green-tick.store'), ['package_id' => $package->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user_verifications', [
            'user_id' => $user->id,
            'status' => UserVerificationStatus::PendingPayment->value,
        ]);
        $this->assertFalse($user->fresh()->payoutEligibility()['followers']['met']);
        $this->assertTrue($user->fresh()->payoutEligibility()['published_posts']['met']);
        $this->assertFalse($user->fresh()->payoutEligibility()['eligible']);
    }

    public function test_published_post_minimum_does_not_block_a_green_tick_purchase(): void
    {
        MonetizationSetting::query()->where('key', 'eligibility_min_followers')->update(['value' => '1']);
        MonetizationSetting::query()->where('key', 'eligibility_min_published_posts')->update(['value' => '1']);

        $user = User::factory()->create();
        $follower = User::factory()->create();
        $user->followers()->attach($follower);
        $package = $this->enableGreenTickPackages()->firstWhere('slug', 'green_tick_monthly');

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('green-tick.store'), ['package_id' => $package->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($user->fresh()->payoutEligibility()['followers']['met']);
        $this->assertFalse($user->fresh()->payoutEligibility()['published_posts']['met']);
        $this->assertFalse($user->fresh()->payoutEligibility()['eligible']);
    }

    public function test_admin_review_shows_qualified_views_for_the_last_thirty_days(): void
    {
        $this->travelTo('2026-09-27 12:00:00');

        MonetizationSetting::query()->where('key', 'eligibility_min_qualified_views_30d')->update(['value' => '3']);

        $admin = User::factory()->admin()->create();
        $verification = UserVerification::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $verification->user_id,
            'status' => 'published',
        ]);
        $viewer = User::factory()->create();

        $this->viewPost($viewer, $post);

        $this->actingAs($admin)
            ->get(route('admin.monetization.green-ticks.show', $verification))
            ->assertOk()
            ->assertSee('1 / 3 qualified views (30 days)')
            ->assertSee('once per viewer every 24 hours')
            ->assertDontSee('not tracked yet')
            ->assertDontSee('Not enforced yet');
    }

    public function test_qualified_view_minimum_blocks_a_green_tick_until_it_is_met(): void
    {
        MonetizationSetting::query()->where('key', 'eligibility_min_followers')->update(['value' => '0']);
        MonetizationSetting::query()->where('key', 'eligibility_min_published_posts')->update(['value' => '0']);
        MonetizationSetting::query()->where('key', 'eligibility_min_qualified_views_30d')->update(['value' => '2']);

        $author = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
        ]);
        $package = $this->enableGreenTickPackages()->firstWhere('slug', 'green_tick_monthly');

        $this->viewPost(User::factory()->create(), $post);

        $eligibility = $author->fresh()->greenTickEligibility();

        $this->assertSame(1, $eligibility['qualified_views']);
        $this->assertSame(2, $eligibility['min_qualified_views']);
        $this->assertTrue($eligibility['views_tracked']);
        $this->assertFalse($eligibility['eligible']);

        $this->actingAs($author)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Get Green Tick')
            ->assertDontSee('qualified views');

        $this->actingAs($author)
            ->from(route('profile.edit'))
            ->post(route('green-tick.store'), ['package_id' => $package->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user_verifications', [
            'user_id' => $author->id,
            'status' => UserVerificationStatus::PendingPayment->value,
        ]);
        $this->assertFalse($author->fresh()->payoutEligibility()['eligible']);

        $this->viewPost(User::factory()->create(), $post);

        $this->assertTrue($author->fresh()->greenTickEligibility()['eligible']);
        $this->assertFalse($author->fresh()->payoutEligibility()['green_tick']['met']);
    }

    public function test_qualified_views_older_than_thirty_days_do_not_count(): void
    {
        $this->travelTo('2026-09-27 12:00:00');

        MonetizationSetting::query()->where('key', 'eligibility_min_followers')->update(['value' => '0']);
        MonetizationSetting::query()->where('key', 'eligibility_min_published_posts')->update(['value' => '0']);
        MonetizationSetting::query()->where('key', 'eligibility_min_qualified_views_30d')->update(['value' => '2']);

        $author = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
        ]);
        $viewer = User::factory()->create();

        $this->travelTo('2026-08-27 12:00:00');
        $this->viewPost($viewer, $post);

        $this->travelTo('2026-08-28 12:00:00');
        $this->viewPost($viewer, $post);

        $this->travelTo('2026-09-27 12:00:00');
        $this->viewPost(User::factory()->create(), $post);

        $eligibility = $author->fresh()->greenTickEligibility();

        $this->assertSame(2, $eligibility['qualified_views']);
        $this->assertTrue($eligibility['eligible']);
    }

    public function test_raw_post_view_counts_do_not_satisfy_the_qualified_view_minimum(): void
    {
        MonetizationSetting::query()->where('key', 'eligibility_min_followers')->update(['value' => '0']);
        MonetizationSetting::query()->where('key', 'eligibility_min_published_posts')->update(['value' => '0']);
        MonetizationSetting::query()->where('key', 'eligibility_min_qualified_views_30d')->update(['value' => '1']);

        $author = User::factory()->create();
        Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'views' => 10000,
        ]);

        $eligibility = $author->greenTickEligibility();

        $this->assertSame(0, $eligibility['qualified_views']);
        $this->assertSame(1, $eligibility['min_qualified_views']);
        $this->assertFalse($eligibility['eligible']);
    }

    public function test_qualified_views_on_unpublished_posts_do_not_count(): void
    {
        MonetizationSetting::query()->where('key', 'eligibility_min_followers')->update(['value' => '0']);
        MonetizationSetting::query()->where('key', 'eligibility_min_published_posts')->update(['value' => '0']);
        MonetizationSetting::query()->where('key', 'eligibility_min_qualified_views_30d')->update(['value' => '1']);

        $author = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
        ]);

        $this->viewPost(User::factory()->create(), $post);
        $post->update(['status' => 'pending']);

        $eligibility = $author->fresh()->greenTickEligibility();

        $this->assertSame(0, $eligibility['qualified_views']);
        $this->assertSame(0, $eligibility['published_posts']);
        $this->assertFalse($eligibility['eligible']);

        $post->update(['status' => 'published']);

        $this->assertSame(1, $author->fresh()->greenTickEligibility()['qualified_views']);
        $this->assertTrue($author->fresh()->greenTickEligibility()['eligible']);
    }

    public function test_pending_green_tick_profile_links_to_payment(): void
    {
        $user = User::factory()->create();
        $this->makeEligible();
        $package = $this->enableGreenTickPackages()->firstWhere('slug', 'green_tick_monthly');

        $this->actingAs($user)
            ->post(route('green-tick.store'), ['package_id' => $package->id]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertSee('Pending payment')
            ->assertSee('Continue to payment')
            ->assertSee(route('orders.show', $order))
            ->assertDontSee('Get Green Tick');
    }

    public function test_payout_eligibility_uses_an_approved_green_tick_and_the_live_admin_minimums(): void
    {
        MonetizationSetting::query()->where('key', 'eligibility_min_followers')->update(['value' => '2']);
        MonetizationSetting::query()->where('key', 'eligibility_min_published_posts')->update(['value' => '1']);
        MonetizationSetting::query()->where('key', 'eligibility_min_qualified_views_30d')->update(['value' => '9999']);

        $user = User::factory()->create();
        $user->followers()->attach(User::factory()->create());
        $user->followers()->attach(User::factory()->create());
        Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
        ]);
        Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'published_at' => null,
        ]);

        $beforeApproval = $user->fresh()->payoutEligibility();

        $this->assertFalse($beforeApproval['eligible']);
        $this->assertFalse($beforeApproval['green_tick']['met']);
        $this->assertSame(2, $beforeApproval['followers']['current']);
        $this->assertSame(2, $beforeApproval['followers']['required']);
        $this->assertTrue($beforeApproval['followers']['met']);
        $this->assertSame(1, $beforeApproval['published_posts']['current']);
        $this->assertTrue($beforeApproval['published_posts']['met']);
        $this->assertFalse($user->fresh()->greenTickEligibility()['eligible']);

        UserVerification::factory()->active()->create(['user_id' => $user->id]);

        $approved = $user->fresh()->payoutEligibility();

        $this->assertTrue($approved['eligible']);
        $this->assertSame(1, $approved['green_tick']['current']);
        $this->assertTrue($approved['green_tick']['met']);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('2 / 2 followers')
            ->assertSee('1 / 1 published posts')
            ->assertSee('Approved Green Tick — Met')
            ->assertDontSee('qualified views');

        MonetizationSetting::query()->where('key', 'eligibility_min_followers')->update(['value' => '3']);

        $raised = $user->fresh()->payoutEligibility();

        $this->assertFalse($raised['eligible']);
        $this->assertSame(3, $raised['followers']['required']);
        $this->assertFalse($raised['followers']['met']);
        $this->assertTrue($raised['green_tick']['met']);
    }

    public function test_payment_does_not_grant_the_badge_or_payout_when_review_is_required(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $user->followers()->attach(User::factory()->create());
        Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
        ]);
        MonetizationSetting::query()->where('key', 'eligibility_min_followers')->update(['value' => '1']);
        MonetizationSetting::query()->where('key', 'eligibility_min_published_posts')->update(['value' => '1']);
        $package = $this->enableGreenTickPackages()->firstWhere('slug', 'green_tick_monthly');

        $this->actingAs($user)
            ->post(route('green-tick.store'), ['package_id' => $package->id])
            ->assertSessionHas('success');

        $order = Order::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.monetization.orders.mark-paid', $order));

        $user->refresh();
        $payout = $user->payoutEligibility();

        $this->assertFalse($user->hasActiveGreenTick());
        $this->assertSame(UserVerificationStatus::PendingReview, $user->currentGreenTickVerification()?->status);
        $this->assertFalse($payout['eligible']);
        $this->assertFalse($payout['green_tick']['met']);
        $this->assertTrue($payout['followers']['met']);
        $this->assertTrue($payout['published_posts']['met']);
    }

    public function test_an_active_green_tick_without_the_count_minimums_is_not_payout_eligible(): void
    {
        MonetizationSetting::query()->where('key', 'green_tick_requires_review')->update(['value' => '0']);

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $package = $this->enableGreenTickPackages()->firstWhere('slug', 'green_tick_monthly');

        $this->actingAs($user)
            ->post(route('green-tick.store'), ['package_id' => $package->id]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.monetization.orders.mark-paid', $order));

        $user->refresh();
        $payout = $user->payoutEligibility();

        $this->assertTrue($user->hasActiveGreenTick());
        $this->assertTrue($payout['green_tick']['met']);
        $this->assertSame(0, $payout['followers']['current']);
        $this->assertSame(500, $payout['followers']['required']);
        $this->assertFalse($payout['followers']['met']);
        $this->assertSame(10, $payout['published_posts']['required']);
        $this->assertFalse($payout['published_posts']['met']);
        $this->assertFalse($payout['eligible']);
    }

    public function test_profile_edit_shows_enabled_packages_when_eligible(): void
    {
        $user = User::factory()->create();
        $this->makeEligible();
        $this->enableGreenTickPackages();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Green Tick Monthly')
            ->assertSee('Get Green Tick');
    }

    /**
     * @return Collection<int, MonetizationPackage>
     */
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

    private function viewPost(User $viewer, Post $post)
    {
        return $this->actingAs($viewer)
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
            ->get(route('posts.show', $post))
            ->assertOk();
    }

    private function meetFollowerAndPostMinimums(User $user): void
    {
        MonetizationSetting::query()->where('key', 'eligibility_min_followers')->update(['value' => '1']);
        MonetizationSetting::query()->where('key', 'eligibility_min_published_posts')->update(['value' => '1']);

        $follower = User::factory()->create();
        $user->followers()->attach($follower);

        Post::factory()->create([
            'user_id' => $user->id,
            'status' => 'published',
        ]);
    }
}
