<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\PostView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualifiedPostViewTest extends TestCase
{
    use RefreshDatabase;

    private const string BrowserUserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function test_opening_a_post_is_counted_once_per_rolling_twenty_four_hours(): void
    {
        $this->travelTo('2026-09-27 20:00:00');

        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'views' => 4,
        ]);

        $this->viewPost($viewer, $post)->assertOk();
        $this->viewPost($viewer, $post)->assertOk();

        $this->assertSame(1, PostView::query()->count());
        $this->assertDatabaseHas('post_views', [
            'post_id' => $post->id,
            'user_id' => $author->id,
            'viewer_user_id' => $viewer->id,
            'viewer_key' => 'user:'.$viewer->id,
            'viewed_on' => '2026-09-27',
        ]);

        $this->travelTo('2026-09-28 19:00:00');

        $this->viewPost($viewer, $post)->assertOk();

        $this->assertSame(1, PostView::query()->count());

        $this->travelTo('2026-09-28 20:00:00');

        $this->viewPost($viewer, $post)->assertOk();

        $this->assertSame(2, PostView::query()->count());
        $this->assertDatabaseHas('post_views', [
            'post_id' => $post->id,
            'viewer_user_id' => $viewer->id,
            'viewed_on' => '2026-09-28',
        ]);
        $this->assertSame(8, (int) $post->fresh()->views);
    }

    public function test_a_feed_beacon_counts_a_logged_in_viewer_once_per_rolling_twenty_four_hours(): void
    {
        $this->travelTo('2026-09-27 20:00:00');

        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'views' => 9,
        ]);

        $this->beacon($viewer, [$post->id])
            ->assertOk()
            ->assertJsonPath('recorded', [$post->id]);

        $this->assertSame(1, PostView::query()->count());
        $this->assertSame(9, (int) $post->fresh()->views);

        $this->travelTo('2026-09-28 19:00:00');

        $this->beacon($viewer, [$post->id])
            ->assertOk()
            ->assertJsonPath('recorded', []);

        $this->assertSame(1, PostView::query()->count());

        $this->travelTo('2026-09-28 20:00:00');

        $this->beacon($viewer, [$post->id])
            ->assertOk()
            ->assertJsonPath('recorded', [$post->id]);

        $this->assertSame(2, PostView::query()->count());
        $this->assertSame(9, (int) $post->fresh()->views);
    }

    public function test_a_feed_beacon_and_a_page_open_within_twenty_four_hours_count_once(): void
    {
        $this->travelTo('2026-09-27 12:00:00');

        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'views' => 10,
        ]);

        $this->beacon($viewer, [$post->id])
            ->assertOk()
            ->assertJsonPath('recorded', [$post->id]);
        $this->viewPost($viewer, $post)->assertOk();

        $other = User::factory()->create();

        $this->viewPost($other, $post)->assertOk();
        $this->beacon($other, [$post->id])
            ->assertOk()
            ->assertJsonPath('recorded', []);

        $this->assertSame(2, PostView::query()->count());
        $this->assertSame(12, (int) $post->fresh()->views);
    }

    public function test_a_batched_beacon_records_each_published_post_once(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $first = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'views' => 1,
        ]);
        $second = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'views' => 2,
        ]);

        $this->beacon($viewer, [$first->id, $second->id, 999999])
            ->assertOk()
            ->assertJsonPath('recorded', [$first->id, $second->id]);

        $this->assertSame(2, PostView::query()->count());
        $this->assertSame(1, (int) $first->fresh()->views);
        $this->assertSame(2, (int) $second->fresh()->views);
    }

    public function test_the_same_signed_in_viewer_counts_on_each_post(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $first = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
        ]);
        $second = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
        ]);

        $this->viewPost($viewer, $first)->assertOk();
        $this->viewPost($viewer, $second)->assertOk();

        $this->assertSame(2, PostView::query()->where('user_id', $author->id)->count());
        $this->assertSame(2, $author->fresh()->greenTickEligibility()['qualified_views']);
    }

    public function test_eligibility_sums_logged_in_qualified_views_on_published_posts(): void
    {
        $this->travelTo('2026-09-27 12:00:00');

        $author = User::factory()->create();
        $first = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'views' => 3,
        ]);
        $second = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'views' => 3,
        ]);
        $viewer = User::factory()->create();
        $other = User::factory()->create();

        $this->beacon($viewer, [$first->id, $second->id])->assertOk();
        $this->beacon($other, [$first->id])->assertOk();
        $this->beacon($viewer, [$first->id])->assertOk()->assertJsonPath('recorded', []);
        $this->viewPost(null, $first)->assertOk();

        PostView::factory()->create([
            'post_id' => $first->id,
            'user_id' => $author->id,
            'viewer_user_id' => null,
            'viewer_key' => 'guest:'.hash('sha256', '203.0.113.50'),
            'viewed_on' => '2026-09-27',
        ]);

        $this->assertSame(3, $author->fresh()->greenTickEligibility()['qualified_views']);
        $this->assertSame(4, (int) $first->fresh()->views);
        $this->assertSame(3, (int) $second->fresh()->views);
    }

    public function test_the_author_does_not_record_a_qualified_view(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'views' => 8,
        ]);

        $this->viewPost($author, $post)->assertOk();
        $this->beacon($author, [$post->id])
            ->assertOk()
            ->assertJsonPath('recorded', []);

        $this->assertDatabaseCount('post_views', 0);
        $this->assertSame(9, (int) $post->fresh()->views);
        $this->assertSame(0, $author->fresh()->greenTickEligibility()['qualified_views']);
    }

    public function test_obvious_bots_do_not_record_a_qualified_view(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'views' => 2,
        ]);

        $this->viewPost(null, $post, '198.51.100.10', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
            ->assertOk();
        $this->viewPost(null, $post, '198.51.100.11', '')
            ->assertOk();
        $this->beacon($viewer, [$post->id], 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
            ->assertOk()
            ->assertJsonPath('recorded', []);
        $this->beacon($viewer, [$post->id], '')
            ->assertOk()
            ->assertJsonPath('recorded', []);

        $this->assertDatabaseCount('post_views', 0);
        $this->assertSame(4, (int) $post->fresh()->views);
    }

    public function test_guests_do_not_record_a_qualified_view(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'views' => 5,
        ]);

        $this->viewPost(null, $post, '203.0.113.20')->assertOk();
        $this->viewPost(null, $post, '203.0.113.21')->assertOk();
        $this->beacon(null, [$post->id])->assertUnauthorized();

        $this->assertDatabaseCount('post_views', 0);
        $this->assertDatabaseMissing('post_views', [
            'viewer_key' => 'guest:'.hash('sha256', '203.0.113.20'),
        ]);
        $this->assertSame(7, (int) $post->fresh()->views);
        $this->assertSame(0, $author->fresh()->greenTickEligibility()['qualified_views']);
    }

    public function test_unpublished_posts_do_not_record_a_qualified_view(): void
    {
        $author = User::factory()->create();
        $stranger = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'pending',
            'views' => 1,
        ]);

        $this->viewPost($author, $post)->assertOk();
        $this->viewPost($stranger, $post)->assertNotFound();
        $this->beacon($stranger, [$post->id])->assertNotFound();

        $this->assertDatabaseCount('post_views', 0);
        $this->assertSame(2, (int) $post->fresh()->views);
    }

    public function test_a_qualified_view_beacon_requires_post_ids(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->withHeader('User-Agent', self::BrowserUserAgent)
            ->postJson(route('posts.qualified-views.store'), [])
            ->assertInvalid([
                'post_ids' => 'The post ids field is required.',
            ]);

        $this->assertDatabaseCount('post_views', 0);
    }

    public function test_qualified_view_beacons_are_throttled(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
        ]);

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->beacon($viewer, [$post->id])->assertOk();
        }

        $this->beacon($viewer, [$post->id])->assertTooManyRequests();
        $this->assertSame(1, PostView::query()->count());
    }

    public function test_post_feeds_mark_cards_for_qualified_view_tracking(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Tips',
            'slug' => 'tips',
            'status' => true,
        ]);
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'category_id' => $category->id,
            'status' => 'published',
            'published_at' => now(),
            'created_at' => now(),
        ]);
        $marker = 'data-qualified-view-post="'.$post->id.'"';

        $this->get(route('posts.index'))
            ->assertOk()
            ->assertDontSee('data-qualified-view-post', false)
            ->assertDontSee('qualified-view-url', false);

        $this->actingAs($viewer)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee($marker, false)
            ->assertSee(route('posts.qualified-views.store'), false);

        $this->actingAs($viewer)
            ->get(route('categories.show', $category))
            ->assertOk()
            ->assertSee($marker, false);

        $this->actingAs($viewer)
            ->get(route('tips.index'))
            ->assertOk()
            ->assertSee($marker, false);

        $this->actingAs($viewer)
            ->get(route('users.show', ['user' => $author, 'tab' => 'stories']))
            ->assertOk()
            ->assertSee($marker, false);

        $this->actingAs($viewer)
            ->get(route('explore.index', ['tab' => 'posts']))
            ->assertOk()
            ->assertSee($marker, false);

        $older = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'created_at' => now()->subDays(5),
            'published_at' => now()->subDays(5),
        ]);

        foreach (range(1, 10) as $minutesAgo) {
            Post::factory()->create([
                'user_id' => $author->id,
                'status' => 'published',
                'created_at' => now()->subMinutes($minutesAgo),
                'published_at' => now()->subMinutes($minutesAgo),
            ]);
        }

        $this->actingAs($viewer)
            ->get(route('posts.index', ['page' => 2, 'partial' => 1]))
            ->assertOk()
            ->assertSee('data-qualified-view-post="'.$older->id.'"', false);
    }

    private function viewPost(?User $viewer, Post $post, string $ip = '203.0.113.10', string $userAgent = self::BrowserUserAgent)
    {
        $pending = $viewer === null ? $this : $this->actingAs($viewer);

        return $pending
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('User-Agent', $userAgent)
            ->get(route('posts.show', $post));
    }

    /**
     * @param  list<int>  $postIds
     */
    private function beacon(?User $viewer, array $postIds, string $userAgent = self::BrowserUserAgent)
    {
        $pending = $viewer === null ? $this : $this->actingAs($viewer);

        return $pending
            ->withHeader('User-Agent', $userAgent)
            ->postJson(route('posts.qualified-views.store'), [
                'post_ids' => $postIds,
            ]);
    }
}
