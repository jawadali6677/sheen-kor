<?php

namespace Tests\Feature;

use App\Enums\AdEventType;
use App\Enums\AdPlacement;
use App\Models\AdEvent;
use App\Models\Advertisement;
use App\Models\Alert;
use App\Models\MonetizationSetting;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedAdvertisingTest extends TestCase
{
    use RefreshDatabase;

    public function test_native_feed_ads_use_the_configured_interval_instead_of_every_five_posts(): void
    {
        $this->configureFeedAds([
            'feed_ad_interval' => '4',
            'feed_ad_jitter' => '0',
            'feed_ad_cooldown_seconds' => '0',
        ]);

        Post::factory(12)->create(['status' => 'published']);

        $pageOne = $this->get(route('posts.index'));
        $pageOne->assertOk();
        $this->assertSame(2, substr_count($pageOne->getContent(), 'Sponsored'));
        $pageOne->assertSee('GreenPath Supply');

        $pageTwo = $this->get(route('posts.index', ['partial' => 1, 'page' => 2]));
        $pageTwo->assertOk();
        $this->assertSame(1, substr_count($pageTwo->getContent(), 'Sponsored'));
        $pageTwo->assertDontSee("What's happening around you?");
    }

    public function test_disabled_post_feed_ads_are_not_inserted(): void
    {
        $this->configureFeedAds([
            'feed_ads_posts_enabled' => '0',
            'feed_ad_interval' => '2',
            'feed_ad_jitter' => '0',
            'feed_ad_cooldown_seconds' => '0',
        ]);

        Post::factory(6)->create(['status' => 'published']);

        $this->get(route('posts.index'))
            ->assertOk()
            ->assertDontSee('Sponsored');
    }

    public function test_alert_feed_ads_respect_their_own_setting(): void
    {
        $this->configureFeedAds([
            'feed_ads_posts_enabled' => '0',
            'feed_ads_alerts_enabled' => '1',
            'feed_ad_interval' => '3',
            'feed_ad_jitter' => '0',
            'feed_ad_cooldown_seconds' => '0',
        ]);

        Alert::factory(6)->create();

        $response = $this->get(route('alerts.index'));
        $response->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'Sponsored'));
    }

    public function test_session_cap_limits_native_feed_ads(): void
    {
        $this->configureFeedAds([
            'feed_ad_interval' => '3',
            'feed_ad_jitter' => '0',
            'feed_ad_session_cap' => '1',
            'feed_ad_cooldown_seconds' => '0',
        ]);

        Post::factory(10)->create(['status' => 'published']);

        $response = $this->get(route('posts.index'));
        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'Sponsored'));
    }

    public function test_daily_cap_limits_native_feed_ads(): void
    {
        $this->configureFeedAds([
            'feed_ad_interval' => '3',
            'feed_ad_jitter' => '0',
            'feed_ad_daily_cap' => '1',
            'feed_ad_cooldown_seconds' => '0',
        ]);

        $advertisement = Advertisement::query()->where('slug', 'demo-bottles')->firstOrFail();
        $user = User::factory()->create();

        AdEvent::query()->create([
            'advertisement_id' => $advertisement->id,
            'user_id' => $user->id,
            'visitor_key' => 'already-capped',
            'type' => AdEventType::Impression,
            'placement' => AdPlacement::FeedPosts,
        ]);

        Post::factory(10)->create(['status' => 'published']);

        $response = $this->actingAs($user)->get(route('posts.index'));
        $response->assertOk();
        $this->assertSame(0, substr_count($response->getContent(), '>Sponsored</p>'));
    }

    public function test_cooldown_prevents_back_to_back_feed_ads(): void
    {
        $this->configureFeedAds([
            'feed_ad_interval' => '3',
            'feed_ad_jitter' => '0',
            'feed_ad_cooldown_seconds' => '3600',
        ]);

        Post::factory(10)->create(['status' => 'published']);

        $response = $this->get(route('posts.index'));
        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), 'Sponsored'));
    }

    public function test_sidebar_ads_follow_the_sidebar_setting(): void
    {
        $this->configureFeedAds([
            'feed_ads_posts_enabled' => '0',
            'sidebar_ads_enabled' => '1',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee('Canopy Collective');

        MonetizationSetting::query()->where('key', 'sidebar_ads_enabled')->update(['value' => '0']);

        $this->actingAs($user)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertDontSee('Canopy Collective');
    }

    public function test_impressions_and_clicks_are_recorded_against_the_stored_advertisement(): void
    {
        $advertisement = Advertisement::query()->where('slug', 'demo-bottles')->firstOrFail();

        $this->postJson(route('ads.impressions.store', $advertisement), [
            'placement' => AdPlacement::FeedPosts->value,
        ])->assertOk()->assertJson(['recorded' => true]);

        $this->get(route('ads.click', [
            'advertisement' => $advertisement,
            'placement' => AdPlacement::FeedPosts->value,
        ]))->assertRedirect('https://example.com/greenpath');

        $this->assertDatabaseHas('ad_events', [
            'advertisement_id' => $advertisement->id,
            'type' => AdEventType::Impression->value,
            'placement' => AdPlacement::FeedPosts->value,
        ]);
        $this->assertDatabaseHas('ad_events', [
            'advertisement_id' => $advertisement->id,
            'type' => AdEventType::Click->value,
        ]);
    }

    public function test_click_destination_cannot_be_changed_from_the_query_string(): void
    {
        $advertisement = Advertisement::query()->where('slug', 'demo-trees')->firstOrFail();

        $this->get(route('ads.click', $advertisement).'?url=https://evil.example')
            ->assertRedirect('https://example.com/canopy');
    }

    public function test_disabled_advertisements_cannot_be_tracked_or_clicked(): void
    {
        $advertisement = Advertisement::query()->where('slug', 'demo-bottles')->firstOrFail();
        $advertisement->forceFill(['is_enabled' => false])->save();

        $this->postJson(route('ads.impressions.store', $advertisement), [
            'placement' => AdPlacement::FeedPosts->value,
        ])->assertNotFound();

        $this->get(route('ads.click', $advertisement))->assertNotFound();
        $this->assertDatabaseCount('ad_events', 0);
    }

    public function test_admins_can_disable_an_advertisement_from_monetization_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $advertisement = Advertisement::query()->where('slug', 'demo-trees')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.monetization.advertisements.update', $advertisement), [
                'is_enabled' => '0',
                'is_feed' => '1',
                'is_sidebar' => '0',
                'is_video' => '0',
                'is_rewarded' => '0',
            ])
            ->assertRedirect(route('admin.monetization.index'));

        $advertisement->refresh();

        $this->assertFalse($advertisement->is_enabled);
        $this->assertTrue($advertisement->is_feed);
        $this->assertFalse($advertisement->is_sidebar);
    }

    public function test_members_cannot_update_advertisements(): void
    {
        $member = User::factory()->create();
        $advertisement = Advertisement::query()->where('slug', 'demo-bottles')->firstOrFail();

        $this->actingAs($member)
            ->patch(route('admin.monetization.advertisements.update', $advertisement), [
                'is_enabled' => '0',
                'is_feed' => '0',
                'is_sidebar' => '0',
                'is_video' => '0',
                'is_rewarded' => '0',
            ])
            ->assertForbidden();

        $this->assertTrue($advertisement->fresh()->is_enabled);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function configureFeedAds(array $values): void
    {
        $defaults = [
            'feed_ads_posts_enabled' => '1',
            'feed_ads_alerts_enabled' => '1',
            'sidebar_ads_enabled' => '0',
            'feed_ad_interval' => '8',
            'feed_ad_jitter' => '0',
            'feed_ad_session_cap' => '0',
            'feed_ad_daily_cap' => '0',
            'feed_ad_cooldown_seconds' => '0',
        ];

        foreach (array_merge($defaults, $values) as $key => $value) {
            MonetizationSetting::query()->where('key', $key)->update(['value' => $value]);
        }
    }
}
