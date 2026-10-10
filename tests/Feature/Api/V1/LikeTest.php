<?php

namespace Tests\Feature\Api\V1;

use App\Events\AlertEngagementUpdated;
use App\Events\PostEngagementUpdated;
use App\Models\Alert;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LikeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_like_and_unlike_a_post_and_the_author_is_notified(): void
    {
        Event::fake([PostEngagementUpdated::class]);

        $author = User::factory()->create();
        $member = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
        ]);

        $this->postJson(route('api.v1.posts.likes.store', $post), [], $this->bearer($member))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('liked', true)
            ->assertJsonPath('likes_count', 1)
            ->assertJsonPath('message', 'You liked this story.');

        $this->assertDatabaseHas('likes', [
            'user_id' => $member->id,
            'likeable_id' => $post->id,
            'likeable_type' => 'post',
        ]);
        $this->assertSame('post_liked', $author->notifications()->first()?->data['kind']);

        Event::assertDispatched(PostEngagementUpdated::class, function (PostEngagementUpdated $event) use ($post): bool {
            return $event->postId === $post->id && $event->likesCount === 1;
        });

        $this->postJson(route('api.v1.posts.likes.store', $post), [], $this->bearer($member))
            ->assertOk()
            ->assertJsonPath('liked', true)
            ->assertJsonPath('likes_count', 1)
            ->assertJsonPath('message', 'You already liked this story.');

        $this->assertSame(1, Like::query()->count());
        $this->assertSame(1, $author->notifications()->count());

        $this->deleteJson(route('api.v1.posts.likes.destroy', $post), [], $this->bearer($member))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('liked', false)
            ->assertJsonPath('likes_count', 0)
            ->assertJsonPath('message', 'You unliked this story.');

        $this->assertDatabaseMissing('likes', [
            'user_id' => $member->id,
            'likeable_id' => $post->id,
        ]);

        Event::assertDispatched(PostEngagementUpdated::class, function (PostEngagementUpdated $event) use ($post): bool {
            return $event->postId === $post->id && $event->likesCount === 0;
        });
    }

    public function test_a_member_can_like_and_unlike_an_alert_and_the_owner_is_notified(): void
    {
        Event::fake([AlertEngagementUpdated::class]);

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $alert = Alert::factory()->create([
            'user_id' => $owner->id,
        ]);

        $this->postJson(route('api.v1.alerts.likes.store', $alert), [], $this->bearer($member))
            ->assertOk()
            ->assertJsonPath('liked', true)
            ->assertJsonPath('likes_count', 1)
            ->assertJsonPath('message', 'You liked this alert.');

        $this->assertSame('alert_liked', $owner->notifications()->first()?->data['kind']);

        Event::assertDispatched(AlertEngagementUpdated::class, function (AlertEngagementUpdated $event) use ($alert): bool {
            return $event->alertId === $alert->id && $event->likesCount === 1;
        });

        $this->deleteJson(route('api.v1.alerts.likes.destroy', $alert), [], $this->bearer($member))
            ->assertOk()
            ->assertJsonPath('liked', false)
            ->assertJsonPath('likes_count', 0)
            ->assertJsonPath('message', 'You unliked this alert.');
    }

    public function test_unpublished_posts_cannot_be_liked(): void
    {
        $member = User::factory()->create();
        $post = Post::factory()->create([
            'status' => 'pending',
            'published_at' => null,
        ]);

        $this->postJson(route('api.v1.posts.likes.store', $post), [], $this->bearer($member))
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'You can only like published stories.');

        $this->assertDatabaseCount('likes', 0);
    }

    public function test_a_disabled_account_cannot_like_a_post(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Pixel 8', ['mobile'])->plainTextToken;
        $user->forceFill(['status' => false])->save();
        $post = Post::factory()->create(['status' => 'published']);

        $this->postJson(route('api.v1.posts.likes.store', $post), [], $this->bearerToken($token))
            ->assertForbidden()
            ->assertJsonPath('message', 'This account has been disabled.');

        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseCount('likes', 0);
    }

    /**
     * @return array<string, string>
     */
    private function bearer(User $user): array
    {
        return $this->bearerToken($user->createToken('Pixel 8', ['mobile'])->plainTextToken);
    }

    /**
     * @return array<string, string>
     */
    private function bearerToken(string $token): array
    {
        Auth::forgetGuards();

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }
}
