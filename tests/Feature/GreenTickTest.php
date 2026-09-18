<?php

namespace Tests\Feature;

use App\Enums\MonetizationPackageType;
use App\Enums\UserVerificationSource;
use App\Enums\UserVerificationStatus;
use App\Models\MonetizationPackage;
use App\Models\MonetizationSetting;
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

    public function test_ineligible_members_cannot_request_a_green_tick(): void
    {
        $user = User::factory()->create();
        $this->enableGreenTickPackages();

        $package = MonetizationPackage::query()->where('slug', 'green_tick_monthly')->firstOrFail();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('green-tick.store'), ['package_id' => $package->id])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('user_verifications', 0);
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
            ->assertSee('You are not eligible to request a Green Tick yet');
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
            ->assertSee('Request Green Tick');
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
}
