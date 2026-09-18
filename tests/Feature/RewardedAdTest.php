<?php

namespace Tests\Feature;

use App\Contracts\RewardedAdVerifier;
use App\Enums\RewardedAdStatus;
use App\Enums\RewardType;
use App\Models\MonetizationSetting;
use App\Models\RewardedAdSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardedAdTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_edit_shows_the_opt_in_when_rewarded_ads_are_enabled(): void
    {
        $this->configureRewardedAds();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Watch a short sponsored video to receive a reward');
    }

    public function test_disabled_rewarded_ads_cannot_be_started(): void
    {
        $this->configureRewardedAds(['rewarded_ads_enabled' => '0']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('rewarded-ads.store'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('rewarded_ad_sessions', 0);
    }

    public function test_members_must_opt_in_to_start_a_session_and_client_reward_values_are_ignored(): void
    {
        $this->configureRewardedAds(['rewarded_value' => '2']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('rewarded-ads.store'), [
                'reward_type' => 'score_points',
                'reward_value' => 999,
            ])
            ->assertRedirect();

        $session = RewardedAdSession::query()->firstOrFail();

        $this->assertSame($user->id, $session->user_id);
        $this->assertSame(RewardedAdStatus::Started, $session->status);
        $this->assertSame(RewardType::ProfileVisibilityCredit, $session->reward_type);
        $this->assertSame(2, $session->reward_value);
        $this->assertSame(0, $user->fresh()->score);
    }

    public function test_unverified_completion_is_rejected_without_a_provider(): void
    {
        $this->configureRewardedAds();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('rewarded-ads.store'));
        $session = RewardedAdSession::query()->firstOrFail();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('rewarded-ads.complete', $session), [
                'provider_reference' => 'fake-js-complete',
                'reward_value' => 50,
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('error');

        $this->assertSame(RewardedAdStatus::Started, $session->fresh()->status);
        $this->assertNull($session->fresh()->completed_at);
        $this->assertSame(0, $user->fresh()->score);
    }

    public function test_verified_completion_stores_the_server_side_reward_snapshot_once(): void
    {
        $this->configureRewardedAds(['rewarded_value' => '3']);
        $this->fakeVerifiedProvider();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('rewarded-ads.store'));
        $session = RewardedAdSession::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('rewarded-ads.complete', $session), [
                'provider_reference' => 'provider-abc',
                'reward_value' => 999,
            ])
            ->assertRedirect();

        $session->refresh();

        $this->assertSame(RewardedAdStatus::Completed, $session->status);
        $this->assertSame(3, $session->reward_value);
        $this->assertSame('provider-abc', $session->provider_reference);
        $this->assertSame(0, $user->fresh()->score);

        $this->actingAs($user)
            ->post(route('rewarded-ads.complete', $session), [
                'provider_reference' => 'provider-abc',
            ])
            ->assertSessionHas('error');

        $this->assertSame(1, RewardedAdSession::query()->where('status', RewardedAdStatus::Completed)->count());
    }

    public function test_the_same_provider_reference_cannot_be_replayed_on_another_session(): void
    {
        $this->configureRewardedAds([
            'rewarded_daily_limit' => '3',
            'rewarded_cooldown_minutes' => '0',
        ]);
        $this->fakeVerifiedProvider();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('rewarded-ads.store'));
        $first = RewardedAdSession::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('rewarded-ads.complete', $first), [
                'provider_reference' => 'shared-ref',
            ]);

        $this->actingAs($user)->post(route('rewarded-ads.store'));
        $second = RewardedAdSession::query()->latest('id')->firstOrFail();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('rewarded-ads.complete', $second), [
                'provider_reference' => 'shared-ref',
            ])
            ->assertSessionHas('error');

        $this->assertSame(RewardedAdStatus::Started, $second->fresh()->status);
    }

    public function test_daily_limit_blocks_additional_starts(): void
    {
        $this->configureRewardedAds([
            'rewarded_daily_limit' => '1',
            'rewarded_cooldown_minutes' => '0',
        ]);
        $this->fakeVerifiedProvider();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('rewarded-ads.store'));
        $session = RewardedAdSession::query()->firstOrFail();
        $this->actingAs($user)->post(route('rewarded-ads.complete', $session), [
            'provider_reference' => 'one',
        ]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('rewarded-ads.store'))
            ->assertSessionHas('error');

        $this->assertSame(1, RewardedAdSession::query()->count());
    }

    public function test_cooldown_blocks_another_start_after_completion(): void
    {
        $this->configureRewardedAds([
            'rewarded_daily_limit' => '5',
            'rewarded_cooldown_minutes' => '60',
        ]);
        $this->fakeVerifiedProvider();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('rewarded-ads.store'));
        $session = RewardedAdSession::query()->firstOrFail();
        $this->actingAs($user)->post(route('rewarded-ads.complete', $session), [
            'provider_reference' => 'cool',
        ]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->post(route('rewarded-ads.store'))
            ->assertSessionHas('error');
    }

    public function test_members_cannot_complete_another_users_session(): void
    {
        $this->configureRewardedAds();
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($owner)->post(route('rewarded-ads.store'));
        $session = RewardedAdSession::query()->firstOrFail();

        $this->actingAs($other)
            ->post(route('rewarded-ads.complete', $session), [
                'provider_reference' => 'stolen',
            ])
            ->assertRedirect();

        $this->assertSame(RewardedAdStatus::Started, $session->fresh()->status);
    }

    public function test_guests_cannot_start_rewarded_ads(): void
    {
        $this->configureRewardedAds();

        $this->post(route('rewarded-ads.store'))
            ->assertRedirect(route('login'));
    }

    /**
     * @param  array<string, string>  $values
     */
    private function configureRewardedAds(array $values = []): void
    {
        $defaults = [
            'rewarded_ads_enabled' => '1',
            'rewarded_daily_limit' => '3',
            'rewarded_cooldown_minutes' => '0',
            'rewarded_type' => RewardType::ProfileVisibilityCredit->value,
            'rewarded_value' => '1',
        ];

        foreach (array_merge($defaults, $values) as $key => $value) {
            MonetizationSetting::query()->where('key', $key)->update(['value' => $value]);
        }
    }

    private function fakeVerifiedProvider(): void
    {
        $this->app->bind(RewardedAdVerifier::class, function () {
            return new class implements RewardedAdVerifier
            {
                public function verify(RewardedAdSession $session, ?string $providerReference): bool
                {
                    return filled($providerReference);
                }
            };
        });
    }
}
