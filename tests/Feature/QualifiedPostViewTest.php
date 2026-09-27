<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualifiedPostViewTest extends TestCase
{
    use RefreshDatabase;

    private const string BrowserUserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function test_a_signed_in_view_is_recorded_once_per_post_per_day(): void
    {
        $this->travelTo('2026-09-27 12:00:00');

        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'views' => 4,
        ]);

        $this->viewPost($viewer, $post, '203.0.113.10')->assertOk();
        $this->viewPost($viewer, $post, '203.0.113.11')->assertOk();

        $this->assertSame(1, PostView::query()->count());
        $this->assertDatabaseHas('post_views', [
            'post_id' => $post->id,
            'user_id' => $author->id,
            'viewer_key' => 'user:'.$viewer->id,
            'viewed_on' => '2026-09-27',
        ]);
        $this->assertSame(6, (int) $post->fresh()->views);

        $this->travel(1)->days();

        $this->viewPost($viewer, $post)->assertOk();

        $this->assertSame(2, PostView::query()->count());
        $this->assertDatabaseHas('post_views', [
            'post_id' => $post->id,
            'viewer_key' => 'user:'.$viewer->id,
            'viewed_on' => '2026-09-28',
        ]);
        $this->assertSame(7, (int) $post->fresh()->views);
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

    public function test_the_author_viewing_their_own_post_does_not_record_a_qualified_view(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'views' => 8,
        ]);

        $this->viewPost($author, $post)->assertOk();

        $this->assertDatabaseCount('post_views', 0);
        $this->assertSame(9, (int) $post->fresh()->views);
        $this->assertSame(0, $author->fresh()->greenTickEligibility()['qualified_views']);
    }

    public function test_obvious_bots_do_not_record_a_qualified_view(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'views' => 2,
        ]);

        $this->viewPost(null, $post, '198.51.100.10', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
            ->assertOk();
        $this->viewPost(null, $post, '198.51.100.11', '')
            ->assertOk();

        $this->assertDatabaseCount('post_views', 0);
        $this->assertSame(4, (int) $post->fresh()->views);
    }

    public function test_guests_are_counted_once_per_ip_address_per_day(): void
    {
        $this->travelTo('2026-09-27 12:00:00');

        $author = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
        ]);

        $this->viewPost(null, $post, '203.0.113.20')->assertOk();
        $this->viewPost(null, $post, '203.0.113.20')->assertOk();
        $this->viewPost(null, $post, '203.0.113.21')->assertOk();

        $this->assertSame(2, PostView::query()->count());
        $this->assertDatabaseHas('post_views', [
            'post_id' => $post->id,
            'user_id' => $author->id,
            'viewer_key' => 'guest:'.hash('sha256', '203.0.113.20'),
            'viewed_on' => '2026-09-27',
        ]);
        $this->assertDatabaseHas('post_views', [
            'viewer_key' => 'guest:'.hash('sha256', '203.0.113.21'),
        ]);
        $this->assertDatabaseMissing('post_views', [
            'viewer_key' => '203.0.113.20',
        ]);
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

        $this->assertDatabaseCount('post_views', 0);
        $this->assertSame(2, (int) $post->fresh()->views);
    }

    private function viewPost(?User $viewer, Post $post, string $ip = '203.0.113.10', string $userAgent = self::BrowserUserAgent)
    {
        $pending = $viewer === null ? $this : $this->actingAs($viewer);

        return $pending
            ->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('User-Agent', $userAgent)
            ->get(route('posts.show', $post));
    }
}
