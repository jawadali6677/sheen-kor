<?php

namespace Tests\Feature;

use App\Enums\ConversationParticipantRole;
use App\Enums\ConversationType;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_the_inbox(): void
    {
        $this->get(route('messages.index'))
            ->assertRedirect(route('login'));
    }

    public function test_direct_conversation_is_reused_for_the_same_pair(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $first = $this->actingAs($alice)
            ->post(route('messages.direct.store'), [
                'user_id' => $bob->id,
            ]);

        $first->assertRedirect();
        $conversation = Conversation::query()->first();
        $this->assertNotNull($conversation);
        $first->assertRedirect(route('messages.show', $conversation));

        $this->actingAs($bob)
            ->post(route('messages.direct.store'), [
                'user_id' => $alice->id,
            ])
            ->assertRedirect(route('messages.show', $conversation));

        $this->assertSame(1, Conversation::query()->count());
        $this->assertSame(ConversationType::Direct, $conversation->fresh()->type);
    }

    public function test_user_cannot_start_a_direct_conversation_with_themselves(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('messages.direct.store'), [
                'user_id' => $user->id,
            ])
            ->assertSessionHasErrors('user_id');

        $this->assertSame(0, Conversation::query()->count());
    }

    public function test_user_cannot_start_a_direct_conversation_with_an_inactive_user(): void
    {
        $user = User::factory()->create();
        $inactive = User::factory()->disabled()->create();

        $this->actingAs($user)
            ->post(route('messages.direct.store'), [
                'user_id' => $inactive->id,
            ])
            ->assertSessionHasErrors('user_id');
    }

    public function test_non_participant_cannot_view_or_send_messages(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = $this->directConversation($alice, $bob);

        $this->actingAs($outsider)
            ->get(route('messages.show', $conversation))
            ->assertForbidden();

        $this->actingAs($outsider)
            ->postJson(route('messages.messages.store', $conversation), [
                'body' => 'Hello',
            ])
            ->assertForbidden();
    }

    public function test_user_can_send_a_message_and_it_is_broadcast(): void
    {
        Event::fake([MessageSent::class]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $conversation = $this->directConversation($alice, $bob);

        $this->actingAs($alice)
            ->postJson(route('messages.messages.store', $conversation), [
                'body' => '  Hello Bob  ',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('chat_message.body', 'Hello Bob');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $alice->id,
            'body' => 'Hello Bob',
        ]);

        $this->assertNotNull($conversation->fresh()->last_message_at);
        $this->assertNotNull(
            $conversation->participants()->where('users.id', $alice->id)->first()?->pivot->last_read_at,
        );

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($alice, $conversation): bool {
            return $event->message->conversation_id === $conversation->id
                && $event->message->user_id === $alice->id
                && $event->message->body === 'Hello Bob';
        });
    }

    public function test_participant_can_fetch_messages_newer_than_an_id(): void
    {
        Event::fake([MessageSent::class]);

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $conversation = $this->directConversation($alice, $bob);

        $first = $conversation->messages()->create([
            'user_id' => $alice->id,
            'body' => 'First',
        ]);

        $second = $conversation->messages()->create([
            'user_id' => $bob->id,
            'body' => 'Second',
        ]);

        $this->actingAs($bob)
            ->getJson(route('messages.messages.index', ['conversation' => $conversation, 'after_id' => $first->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('messages.0.id', $second->id)
            ->assertJsonPath('messages.0.body', 'Second');
    }

    public function test_sending_a_message_succeeds_without_a_socket_id(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $conversation = $this->directConversation($alice, $bob);

        $this->actingAs($alice)
            ->withHeaders(['X-Socket-ID' => ''])
            ->postJson(route('messages.messages.store', $conversation), [
                'body' => 'Hello from the group',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('chat_message.body', 'Hello from the group');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'user_id' => $alice->id,
            'body' => 'Hello from the group',
        ]);
    }

    public function test_unread_count_clears_when_the_recipient_opens_the_thread(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $conversation = $this->directConversation($alice, $bob);

        Event::fake([MessageSent::class]);

        $this->actingAs($alice)
            ->postJson(route('messages.messages.store', $conversation), [
                'body' => 'Are you there?',
            ])
            ->assertCreated();

        $this->actingAs($bob)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertSee('Are you there?');

        $this->assertSame(1, $bob->fresh()->unreadConversationCount());

        $this->actingAs($bob)
            ->get(route('messages.show', $conversation))
            ->assertOk();

        $this->assertSame(0, $bob->fresh()->unreadConversationCount());
    }

    public function test_user_can_create_a_group_and_only_admins_can_add_members(): void
    {
        $owner = User::factory()->create();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $memberC = User::factory()->create();

        $this->follow($owner, $memberA);
        $this->follow($owner, $memberB);
        $this->follow($owner, $memberC);

        $this->actingAs($owner)
            ->post(route('messages.groups.store'), [
                'title' => 'Neighborhood watch',
                'user_ids' => [$memberA->id, $memberB->id],
            ])
            ->assertRedirect();

        $conversation = Conversation::query()->where('type', ConversationType::Group)->first();
        $this->assertNotNull($conversation);
        $this->assertSame('Neighborhood watch', $conversation->title);
        $this->assertTrue($conversation->isAdmin($owner));
        $this->assertSame(3, $conversation->participants()->count());

        $this->actingAs($memberA)
            ->post(route('messages.participants.store', $conversation), [
                'user_ids' => [$memberC->id],
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('messages.participants.store', $conversation), [
                'user_ids' => [$memberC->id],
            ])
            ->assertRedirect(route('messages.show', $conversation));

        $this->assertTrue($conversation->fresh()->hasParticipant($memberC));
    }

    public function test_user_cannot_add_someone_they_do_not_follow_to_a_group(): void
    {
        $owner = User::factory()->create();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $stranger = User::factory()->create();

        $this->follow($owner, $memberA);
        $this->follow($owner, $memberB);

        $this->actingAs($owner)
            ->post(route('messages.groups.store'), [
                'title' => 'Neighborhood watch',
                'user_ids' => [$memberA->id, $stranger->id],
            ])
            ->assertSessionHasErrors('user_ids');

        $this->actingAs($owner)
            ->post(route('messages.groups.store'), [
                'title' => 'Neighborhood watch',
                'user_ids' => [$memberA->id, $memberB->id],
            ])
            ->assertRedirect();

        $conversation = Conversation::query()->where('type', ConversationType::Group)->first();

        $this->actingAs($owner)
            ->post(route('messages.participants.store', $conversation), [
                'user_ids' => [$stranger->id],
            ])
            ->assertSessionHasErrors('user_ids');

        $this->assertFalse($conversation->fresh()->hasParticipant($stranger));
    }

    public function test_leaving_as_the_last_admin_promotes_another_member(): void
    {
        $owner = User::factory()->create();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();

        $this->follow($owner, $memberA);
        $this->follow($owner, $memberB);

        $this->actingAs($owner)
            ->post(route('messages.groups.store'), [
                'title' => 'Cleanup crew',
                'user_ids' => [$memberA->id, $memberB->id],
            ])
            ->assertRedirect();

        $conversation = Conversation::query()->first();

        $this->actingAs($owner)
            ->delete(route('messages.participants.destroy', $conversation))
            ->assertRedirect(route('messages.index'));

        $this->assertFalse($conversation->fresh()->hasParticipant($owner));
        $this->assertTrue($conversation->fresh()->isAdmin($memberA));
    }

    public function test_group_admin_can_remove_a_member_and_delete_the_group(): void
    {
        $owner = User::factory()->create();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();

        $this->follow($owner, $memberA);
        $this->follow($owner, $memberB);

        $this->actingAs($owner)
            ->post(route('messages.groups.store'), [
                'title' => 'Cleanup crew',
                'user_ids' => [$memberA->id, $memberB->id],
            ])
            ->assertRedirect();

        $conversation = Conversation::query()->where('type', ConversationType::Group)->first();

        $this->actingAs($memberA)
            ->delete(route('messages.participants.remove', [$conversation, $memberB]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('messages.participants.remove', [$conversation, $memberB]))
            ->assertRedirect(route('messages.show', $conversation));

        $this->assertFalse($conversation->fresh()->hasParticipant($memberB));

        $this->actingAs($owner)
            ->delete(route('messages.destroy', $conversation))
            ->assertRedirect(route('messages.index'));

        $this->assertDatabaseMissing('conversations', [
            'id' => $conversation->id,
        ]);
    }

    public function test_project_admin_can_remove_a_member_and_delete_a_group_they_are_not_in(): void
    {
        $owner = User::factory()->create();
        $memberA = User::factory()->create();
        $memberB = User::factory()->create();
        $projectAdmin = User::factory()->admin()->create();

        $this->follow($owner, $memberA);
        $this->follow($owner, $memberB);

        $this->actingAs($owner)
            ->post(route('messages.groups.store'), [
                'title' => 'City crew',
                'user_ids' => [$memberA->id, $memberB->id],
            ])
            ->assertRedirect();

        $conversation = Conversation::query()->where('type', ConversationType::Group)->first();

        $this->actingAs($projectAdmin)
            ->get(route('messages.show', $conversation))
            ->assertOk();

        $this->actingAs($projectAdmin)
            ->delete(route('messages.participants.remove', [$conversation, $memberB]))
            ->assertRedirect(route('messages.show', $conversation));

        $this->assertFalse($conversation->fresh()->hasParticipant($memberB));

        $this->actingAs($projectAdmin)
            ->delete(route('messages.destroy', $conversation))
            ->assertRedirect(route('messages.index'));

        $this->assertDatabaseMissing('conversations', [
            'id' => $conversation->id,
        ]);
    }

    public function test_direct_conversations_cannot_be_deleted(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $conversation = $this->directConversation($alice, $bob);

        $this->actingAs($alice)
            ->delete(route('messages.destroy', $conversation))
            ->assertForbidden();
    }

    public function test_participant_can_authorize_the_conversation_channel(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = $this->directConversation($alice, $bob);

        $this->actingAs($alice)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-conversations.'.$conversation->id,
            ])
            ->assertOk();

        $this->actingAs($outsider)
            ->post('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-conversations.'.$conversation->id,
            ])
            ->assertForbidden();
    }

    public function test_message_preview_is_escaped_in_the_inbox(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $conversation = $this->directConversation($alice, $bob);

        Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $alice->id,
            'body' => '<script>alert("xss")</script>',
        ]);

        $this->actingAs($bob)
            ->get(route('messages.index'))
            ->assertOk()
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
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

    private function follow(User $actor, User $other): void
    {
        $actor->followings()->attach($other->id);
    }
}
