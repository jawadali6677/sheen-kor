<?php

namespace Tests\Feature;

use App\Enums\AdEventType;
use App\Enums\AdPlacement;
use App\Models\AdEvent;
use App\Models\Advertisement;
use App\Models\MonetizationSetting;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoInterstitialTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_interstitials_are_rejected_when_disabled(): void
    {
        $this->configureVideoAds(['video_ads_enabled' => '0']);
        $post = Post::factory()->create(['status' => 'published']);

        $this->postJson(route('ads.video-interstitials.store'), [
            'source_type' => 'post',
            'source_id' => $post->id,
            'duration_seconds' => 45,
        ])->assertOk()->assertJson([
            'allowed' => false,
            'reason' => 'disabled',
        ]);
    }

    public function test_short_videos_do_not_qualify(): void
    {
        $this->configureVideoAds();
        $post = Post::factory()->create(['status' => 'published']);

        $this->postJson(route('ads.video-interstitials.store'), [
            'source_type' => 'post',
            'source_id' => $post->id,
            'duration_seconds' => 10,
        ])->assertOk()->assertJson([
            'allowed' => false,
            'reason' => 'duration',
        ]);
    }

    public function test_eligible_long_videos_can_receive_one_interstitial(): void
    {
        $this->configureVideoAds();
        $post = Post::factory()->create(['status' => 'published']);

        $this->postJson(route('ads.video-interstitials.store'), [
            'source_type' => 'post',
            'source_id' => $post->id,
            'duration_seconds' => 30,
        ])->assertOk()->assertJson([
            'allowed' => true,
            'reason' => 'ok',
        ])->assertJsonPath('advertisement.slug', 'demo-video-interstitial');
    }

    public function test_the_same_source_cannot_show_consecutive_interstitials(): void
    {
        $this->configureVideoAds();
        $post = Post::factory()->create(['status' => 'published']);

        $this->postJson(route('ads.video-interstitials.store'), [
            'source_type' => 'post',
            'source_id' => $post->id,
            'duration_seconds' => 30,
        ])->assertJson(['allowed' => true]);

        $this->postJson(route('ads.video-interstitials.store'), [
            'source_type' => 'post',
            'source_id' => $post->id,
            'duration_seconds' => 30,
        ])->assertJson([
            'allowed' => false,
            'reason' => 'duplicate_source',
        ]);
    }

    public function test_cooldown_blocks_a_second_interstitial(): void
    {
        $this->configureVideoAds([
            'video_interstitial_cooldown_seconds' => '3600',
        ]);

        $first = Post::factory()->create(['status' => 'published']);
        $second = Post::factory()->create(['status' => 'published']);

        $this->postJson(route('ads.video-interstitials.store'), [
            'source_type' => 'post',
            'source_id' => $first->id,
            'duration_seconds' => 30,
        ])->assertJson(['allowed' => true]);

        $this->postJson(route('ads.video-interstitials.store'), [
            'source_type' => 'post',
            'source_id' => $second->id,
            'duration_seconds' => 30,
        ])->assertJson([
            'allowed' => false,
            'reason' => 'capped',
        ]);
    }

    public function test_session_cap_limits_interstitials(): void
    {
        $this->configureVideoAds([
            'video_interstitial_session_cap' => '1',
            'video_interstitial_cooldown_seconds' => '0',
        ]);

        $first = Post::factory()->create(['status' => 'published']);
        $second = Post::factory()->create(['status' => 'published']);

        $this->postJson(route('ads.video-interstitials.store'), [
            'source_type' => 'post',
            'source_id' => $first->id,
            'duration_seconds' => 30,
        ])->assertJson(['allowed' => true]);

        $this->postJson(route('ads.video-interstitials.store'), [
            'source_type' => 'post',
            'source_id' => $second->id,
            'duration_seconds' => 30,
        ])->assertJson([
            'allowed' => false,
            'reason' => 'capped',
        ]);
    }

    public function test_daily_cap_limits_interstitials(): void
    {
        $this->configureVideoAds([
            'video_interstitial_daily_cap' => '1',
            'video_interstitial_cooldown_seconds' => '0',
        ]);

        $user = User::factory()->create();
        $advertisement = Advertisement::query()->where('slug', 'demo-video-interstitial')->firstOrFail();

        AdEvent::query()->create([
            'advertisement_id' => $advertisement->id,
            'user_id' => $user->id,
            'visitor_key' => 'prior',
            'type' => AdEventType::Impression,
            'placement' => AdPlacement::VideoInterstitial,
        ]);

        $post = Post::factory()->create(['status' => 'published']);

        $this->actingAs($user)
            ->postJson(route('ads.video-interstitials.store'), [
                'source_type' => 'post',
                'source_id' => $post->id,
                'duration_seconds' => 30,
            ])
            ->assertJson([
                'allowed' => false,
                'reason' => 'capped',
            ]);
    }

    public function test_post_show_does_not_insert_an_interstitial_on_page_load(): void
    {
        $this->configureVideoAds();
        $post = Post::factory()->create(['status' => 'published']);

        $this->get(route('posts.show', $post))
            ->assertOk()
            ->assertDontSee('demo-video-interstitial', false);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function configureVideoAds(array $values = []): void
    {
        $defaults = [
            'video_ads_enabled' => '1',
            'video_interstitial_min_seconds' => '15',
            'video_interstitial_cooldown_seconds' => '0',
            'video_interstitial_session_cap' => '0',
            'video_interstitial_daily_cap' => '0',
        ];

        foreach (array_merge($defaults, $values) as $key => $value) {
            MonetizationSetting::query()->where('key', $key)->update(['value' => $value]);
        }
    }
}
