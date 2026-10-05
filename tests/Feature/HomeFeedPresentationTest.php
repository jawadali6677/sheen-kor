<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeFeedPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_feed_shows_real_community_counts_and_keeps_existing_actions(): void
    {
        $this->travelTo('2026-09-29 09:00:00');

        $user = User::factory()->create(['name' => 'Jawad Ali']);
        User::factory()->disabled()->create();

        Post::factory()->count(2)->create([
            'user_id' => $user->id,
            'status' => 'published',
            'published_at' => now(),
            'title' => 'Neighborhood cleanup notes',
        ]);

        Alert::factory()->count(4)->create([
            'user_id' => $user->id,
            'status' => 'fixed',
        ]);
        Alert::factory()->create([
            'user_id' => $user->id,
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->get(route('posts.index'));

        $response->assertOk();
        $response->assertSee('Good morning, Jawad');
        $response->assertSee('Share something green and inspire your community.');
        $response->assertSee("What's on your mind?", false);
        $response->assertSee('data-feed-composer-bar', false);
        $response->assertSee('data-feed-post-status', false);
        $response->assertSee("open-post-composer', { intent: 'photo' }", false);
        $response->assertSee("open-post-composer', { intent: 'video' }", false);
        $response->assertSee("open-post-composer', { intent: 'camera' }", false);
        $response->assertSee('id="category-filter"', false);
        $response->assertSee('Your activity');
        $response->assertSee('My posts');
        $response->assertSee('My alerts');
        $response->assertSee('My market');
        $response->assertSee(route('users.show', ['user' => $user, 'tab' => 'alerts']), false);
        $response->assertSee(route('market.mine'), false);
        $response->assertSee(route('explore.index'), false);
        $response->assertSee('Search posts, people, places...', false);
        $response->assertSee('data-home-sidebar', false);
        $response->assertSee('bg-emerald-100', false);
        $response->assertSee('lg:bg-emerald-50', false);
        $response->assertSee('lg:text-purple-600', false);
        $response->assertSee('lg:bg-forest-800', false);
        $response->assertSee('from-emerald-50 via-white to-lime-50', false);
        $response->assertSee('Community Impact');
        $response->assertSee('Alerts fixed');
        $response->assertSee('Active members');
        $response->assertSee('data-community-stat="stories">2<', false);
        $response->assertSee('data-community-stat="alerts-fixed">4<', false);
        $response->assertSee('data-community-stat="members">1<', false);
        $response->assertSee('Top contributors');
        $response->assertSee('Create post');
        $response->assertSee('Create alert');
        $response->assertSee('Create listing');
        $response->assertSee('Hide advertisement', false);
        $response->assertDontSee('Trees planted');
        $response->assertDontSee('>Photos<', false);
        $response->assertDontSee('>Videos<', false);
        $response->assertDontSee('>Location<', false);
        $response->assertDontSee('>Share<', false);
        $response->assertDontSee('>Latest<', false);
    }

    public function test_other_pages_use_the_home_feed_theme(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('explore.index'));

        $response->assertSee('Your activity');
        $response->assertSee('data-home-sidebar', false);
        $response->assertSee('Community Impact');
        $response->assertSee('Top contributors');
        $response->assertSee('Search posts, people, places...', false);
        $response->assertSee('Greener Community', false);
        $response->assertSee(
            'href="'.route('explore.index').'" class="flex items-center gap-3 rounded-xl bg-emerald-100 px-3 py-2.5 text-forest-900 shadow-sm"',
            false,
        );
    }

    public function test_leaderboard_uses_the_feed_card_theme(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('leaderboard.index'));

        $response->assertSee('sk-card', false);
        $response->assertSee('Scores');
        $response->assertSee('data-home-sidebar', false);
        $response->assertDontSee('text-gray-800 leading-tight', false);
    }

    public function test_guests_still_see_the_story_feed_without_member_sidebars(): void
    {
        Post::factory()->create([
            'status' => 'published',
            'published_at' => now(),
            'title' => 'Public forest story',
        ]);

        $response = $this->get(route('posts.index'));

        $response->assertOk();
        $response->assertSee('Public forest story');
        $response->assertSee('Stories from your community');
        $response->assertSee('id="category-filter"', false);
        $response->assertDontSee('Good morning');
        $response->assertDontSee('Your activity');
        $response->assertDontSee('data-home-sidebar', false);
        $response->assertDontSee('Community Impact');
    }

    public function test_alert_login_and_profile_forms_use_shared_green_controls(): void
    {
        $user = User::factory()->create();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('sk-label', false)
            ->assertSee('sk-input', false)
            ->assertSee('sk-check', false)
            ->assertSee('btn-primary', false);

        $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])
            ->assertOk()
            ->assertSee('sk-error', false);

        $this->actingAs($user)
            ->get(route('alerts.create'))
            ->assertOk()
            ->assertSee('sk-input', false)
            ->assertSee('btn-primary', false)
            ->assertSee('btn-secondary', false)
            ->assertSee('sk-label', false);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('sk-input', false)
            ->assertSee('sk-label', false)
            ->assertSee('btn-primary', false);
    }
}
