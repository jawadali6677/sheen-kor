<?php

namespace Tests\Feature\Api\V1;

use App\Models\Post;
use App\Models\PostView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class QualifiedViewTest extends TestCase
{
    use RefreshDatabase;

    private const string BrowserUserAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function test_a_member_can_record_a_batch_of_qualified_views(): void
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
        $pending = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'pending',
            'published_at' => null,
        ]);
        $own = Post::factory()->create([
            'user_id' => $viewer->id,
            'status' => 'published',
        ]);

        $this->postJson(route('api.v1.posts.qualified-views'), [
            'post_ids' => [$first->id, $pending->id, 999999, $own->id, $second->id],
        ], $this->bearer($viewer))
            ->assertOk()
            ->assertJsonPath('recorded', [$first->id, $second->id]);

        $this->assertDatabaseHas('post_views', [
            'post_id' => $first->id,
            'viewer_user_id' => $viewer->id,
        ]);
        $this->assertDatabaseMissing('post_views', [
            'post_id' => $pending->id,
        ]);
        $this->assertSame(2, PostView::query()->count());
    }

    public function test_a_qualified_view_batch_is_limited_to_twenty_ids(): void
    {
        $viewer = User::factory()->create();

        $this->postJson(route('api.v1.posts.qualified-views'), [
            'post_ids' => range(1, 21),
        ], $this->bearer($viewer))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('post_ids');

        $this->assertDatabaseCount('post_views', 0);
    }

    public function test_a_batch_with_no_published_post_is_not_found(): void
    {
        $viewer = User::factory()->create();
        $pending = Post::factory()->create([
            'status' => 'pending',
            'published_at' => null,
        ]);

        $this->postJson(route('api.v1.posts.qualified-views'), [
            'post_ids' => [$pending->id, 999999],
        ], $this->bearer($viewer))
            ->assertNotFound();

        $this->assertDatabaseCount('post_views', 0);
    }

    public function test_qualified_view_beacons_use_the_qualified_views_throttle(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
        ]);

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson(route('api.v1.posts.qualified-views'), [
                'post_ids' => [$post->id],
            ], $this->bearer($viewer))->assertOk();
        }

        $this->postJson(route('api.v1.posts.qualified-views'), [
            'post_ids' => [$post->id],
        ], $this->bearer($viewer))->assertTooManyRequests();

        $this->assertSame(1, PostView::query()->count());
    }

    /**
     * @return array<string, string>
     */
    private function bearer(User $user): array
    {
        Auth::forgetGuards();

        return [
            'Authorization' => 'Bearer '.$user->createToken('Pixel 8', ['mobile'])->plainTextToken,
            'Accept' => 'application/json',
            'User-Agent' => self::BrowserUserAgent,
        ];
    }
}
