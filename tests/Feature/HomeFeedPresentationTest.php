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

    public function test_other_pages_keep_the_existing_sidebar_labels(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('explore.index'));

        $response->assertOk();
        $response->assertSee('Top scores');
        $response->assertDontSee('Your activity');
        $response->assertDontSee('data-home-sidebar', false);
        $response->assertDontSee('Community Impact');
        $response->assertDontSee('Top contributors');
        $response->assertDontSee('Search posts, people, places...', false);
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
}
