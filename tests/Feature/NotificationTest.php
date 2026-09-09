<?php

namespace Tests\Feature;

use App\Enums\ConversationParticipantRole;
use App\Events\MessageSent;
use App\Events\UserNotificationBroadcasted;
use App\Models\Alert;
use App\Models\Conversation;
use App\Models\Post;
use App\Models\User;
use App\Notifications\ContentCommented;
use App\Notifications\ContentLiked;
use App\Notifications\ConversationMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mark_notifications_read(): void
    {
        $this->post('/notifications/not-a-real-id/read')
            ->assertRedirect(route('login'));

        $this->post(route('notifications.read-all'))
            ->assertRedirect(route('login'));
    }

    public function test_liking_a_post_notifies_the_author_but_not_the_actor(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        $post = $this->publishedPost($author);

        $this->actingAs($reader)
            ->postJson(route('posts.likes.store', $post))
            ->assertOk()
            ->assertJsonPath('likes_count', 1);

        $this->assertSame(1, $author->notifications()->count());
        $this->assertSame(0, $reader->notifications()->count());
        $this->assertSame(ContentLiked::class, $author->notifications()->first()?->type);
        $this->assertSame('post_liked', $author->notifications()->first()?->data['kind']);
    }

    public function test_liking_a_post_broadcasts_the_inbox_event_immediately(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        $post = $this->publishedPost($author);

        Event::fake([UserNotificationBroadcasted::class]);

        $this->actingAs($reader)
            ->postJson(route('posts.likes.store', $post))
            ->assertOk();

        Event::assertDispatched(UserNotificationBroadcasted::class, function (UserNotificationBroadcasted $event) use ($author): bool {
            return $event->userId === $author->id
                && ($event->payload['kind'] ?? null) === 'post_liked';
        });
    }

    public function test_liking_an_alert_notifies_the_owner(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $alert = Alert::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($reader)
            ->postJson(route('alerts.likes.store', $alert))
            ->assertOk()
            ->assertJsonPath('likes_count', 1);

        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame('alert_liked', $owner->notifications()->first()?->data['kind']);
    }

    public function test_liking_your_own_post_does_not_create_a_notification(): void
    {
        $author = User::factory()->create();
        $post = $this->publishedPost($author);

        $this->actingAs($author)
            ->postJson(route('posts.likes.store', $post))
            ->assertOk();

        $this->assertSame(0, $author->notifications()->count());
    }

    public function test_liking_the_same_post_twice_does_not_duplicate_the_notification(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        $post = $this->publishedPost($author);

        $this->actingAs($reader)->postJson(route('posts.likes.store', $post))->assertOk();
        $this->actingAs($reader)->postJson(route('posts.likes.store', $post))->assertOk();

        $this->assertSame(1, $author->notifications()->count());
    }

    public function test_commenting_on_an_alert_notifies_the_owner(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $alert = Alert::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($reader)
            ->postJson(route('alerts.comments.store', $alert), [
                'content' => 'Stay safe out there.',
            ])
            ->assertCreated();

        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame(ContentCommented::class, $owner->notifications()->first()?->type);
        $this->assertSame('alert_commented', $owner->notifications()->first()?->data['kind']);
    }

    public function test_sending_a_chat_message_notifies_the_other_participant_not_the_sender(): void
    {
        Event::fake([MessageSent::class]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $conversation = $this->directConversation($alice, $bob);

        $this->actingAs($alice)
            ->postJson(route('messages.messages.store', $conversation), [
                'body' => 'Hello Bob',
            ])
            ->assertCreated();

        $this->assertSame(0, $alice->notifications()->count());
        $this->assertSame(1, $bob->notifications()->count());
        $this->assertSame(ConversationMessageReceived::class, $bob->notifications()->first()?->type);
        $this->assertSame($conversation->id, $bob->notifications()->first()?->data['conversation_id']);
    }

    public function test_opening_a_conversation_marks_its_message_notifications_read(): void
    {
        Event::fake([MessageSent::class]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $conversation = $this->directConversation($alice, $bob);

        $this->actingAs($alice)
            ->postJson(route('messages.messages.store', $conversation), [
                'body' => 'Hello Bob',
            ])
            ->assertCreated();

        $this->assertNull($bob->notifications()->first()?->read_at);

        $this->actingAs($bob)
            ->get(route('messages.show', $conversation))
            ->assertOk();

        $this->assertNotNull($bob->fresh()->notifications()->first()?->read_at);
    }

    public function test_user_can_mark_a_notification_read_and_cannot_mark_another_users_notification(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        $stranger = User::factory()->create();
        $post = $this->publishedPost($author);

        $this->actingAs($reader)->postJson(route('posts.likes.store', $post))->assertOk();

        $notification = $author->notifications()->first();
        $this->assertNotNull($notification);

        $this->actingAs($stranger)
            ->postJson(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->actingAs($author)
            ->postJson(route('notifications.read', $notification->id))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_read(): void
    {
        $author = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $post = $this->publishedPost($author);

        $this->actingAs($first)->postJson(route('posts.likes.store', $post))->assertOk();
        $this->actingAs($second)->postJson(route('posts.likes.store', $post))->assertOk();

        $this->assertSame(2, $author->unreadNotifications()->count());

        $this->actingAs($author)
            ->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $author->fresh()->unreadNotifications()->count());
    }

    public function test_user_channel_authorizes_only_the_matching_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-App.Models.User.'.$user->id,
            ])
            ->assertOk();

        $this->actingAs($other)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-App.Models.User.'.$user->id,
            ])
            ->assertForbidden();
    }

    private function publishedPost(User $author): Post
    {
        return Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    private function directConversation(User $first, User $second): Conversation
    {
        $conversation = Conversation::factory()->direct()->create([
            'pair_key' => Conversation::pairKey($first->id, $second->id),
            'created_by' => $first->id,
        ]);

        $conversation->participants()->attach([
            $first->id => ['role' => ConversationParticipantRole::Member->value],
            $second->id => ['role' => ConversationParticipantRole::Member->value],
        ]);

        return $conversation;
    }
}
