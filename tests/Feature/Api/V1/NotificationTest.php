<?php

namespace Tests\Feature\Api\V1;

use App\Models\Post;
use App\Models\User;
use App\Notifications\ContentLiked;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_are_newest_first_with_iso_timestamps_kind_and_ids(): void
    {
        $this->travelTo('2026-10-06 12:00:00');

        $author = User::factory()->create();
        $actor = User::factory()->create();
        $olderPost = Post::factory()->create(['user_id' => $author->id, 'title' => 'Older']);
        $newerPost = Post::factory()->create(['user_id' => $author->id, 'title' => 'Newer']);

        $this->travelTo('2026-10-06 11:00:00');
        $author->notify(new ContentLiked($actor, $olderPost));

        $this->travelTo('2026-10-06 12:00:00');
        $author->notify(new ContentLiked($actor, $newerPost));

        $token = $author->createToken('Pixel 8', ['mobile'])->plainTextToken;

        $this->getJson(route('api.v1.notifications.index'), $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.kind', 'post_liked')
            ->assertJsonPath('data.0.post_id', $newerPost->id)
            ->assertJsonPath('data.0.actor_id', $actor->id)
            ->assertJsonPath('data.0.created_at', '2026-10-06T12:00:00+00:00')
            ->assertJsonPath('data.0.read_at', null)
            ->assertJsonPath('data.1.post_id', $olderPost->id);

        $this->getJson(route('api.v1.notifications.unread-count'), $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('count', 2);
    }

    public function test_a_member_can_mark_one_notification_and_then_all_of_them_read(): void
    {
        $this->travelTo('2026-10-06 12:00:00');

        $user = User::factory()->create();
        $actor = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $user->notify(new ContentLiked($actor, $post));
        $second = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ContentLiked::class,
            'data' => [
                'kind' => 'post_liked',
                'title' => 'Another like',
                'body' => 'Body',
                'post_id' => $post->id,
                'actor_id' => $actor->id,
            ],
        ]);
        $first = $user->notifications()->whereKeyNot($second->id)->firstOrFail();
        $token = $user->createToken('Pixel 8', ['mobile'])->plainTextToken;

        $this->postJson(route('api.v1.notifications.read', $first->id), [], $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('notification.id', $first->id)
            ->assertJsonPath('notification.read_at', '2026-10-06T12:00:00+00:00')
            ->assertJsonPath('unread_count', 1);

        $this->postJson(route('api.v1.notifications.read-all'), [], $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($second->fresh()->read_at);
        $this->getJson(route('api.v1.notifications.unread-count'), $this->bearer($token))
            ->assertJsonPath('count', 0);
    }

    public function test_a_member_cannot_read_another_members_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $actor = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);
        $owner->notify(new ContentLiked($actor, $post));
        $notification = $owner->notifications()->firstOrFail();
        $token = $other->createToken('Pixel 8', ['mobile'])->plainTextToken;

        $this->postJson(route('api.v1.notifications.read', $notification->id), [], $this->bearer($token))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);

        $this->postJson(route('api.v1.notifications.read-all'), [], $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_notification_routes_require_a_token(): void
    {
        $this->getJson(route('api.v1.notifications.index'))->assertUnauthorized();
        $this->getJson(route('api.v1.notifications.unread-count'))->assertUnauthorized();
        $this->postJson(route('api.v1.notifications.read-all'))->assertUnauthorized();
    }

    /**
     * @return array<string, string>
     */
    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }
}
