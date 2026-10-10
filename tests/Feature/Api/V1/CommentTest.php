<?php

namespace Tests\Feature\Api\V1;

use App\Events\AlertEngagementUpdated;
use App\Events\PostEngagementUpdated;
use App\Models\Alert;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_comment_reply_edit_and_delete_on_a_post(): void
    {
        Event::fake([PostEngagementUpdated::class]);

        $author = User::factory()->create();
        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'title' => 'River trees',
            'status' => 'published',
        ]);

        $created = $this->postJson(route('api.v1.posts.comments.store', $post), [
            'content' => '<b>Great</b> story to read.',
        ], $this->bearer($member))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Your comment has been added.')
            ->assertJsonPath('comment.content', 'Great story to read.')
            ->assertJsonPath('comment.can_edit', true)
            ->assertJsonPath('comments_count', 1);

        $commentId = $created->json('comment.id');
        $this->assertSame('post_commented', $author->notifications()->first()?->data['kind']);

        Event::assertDispatched(PostEngagementUpdated::class, function (PostEngagementUpdated $event) use ($post): bool {
            return $event->postId === $post->id && $event->commentsCount === 1;
        });

        $reply = $this->postJson(route('api.v1.posts.comments.store', $post), [
            'content' => 'This is a reply.',
            'parent_id' => $commentId,
        ], $this->bearer($member))
            ->assertCreated()
            ->assertJsonPath('message', 'Your reply has been added.')
            ->assertJsonPath('comment.parent_id', $commentId)
            ->assertJsonPath('comments_count', 2);

        $this->postJson(route('api.v1.posts.comments.store', $post), [
            'content' => 'Reply to the child stays on this branch.',
            'parent_id' => $reply->json('comment.id'),
        ], $this->bearer($member))
            ->assertCreated()
            ->assertJsonPath('comment.parent_id', $commentId);

        $this->getJson(route('api.v1.posts.comments.index', $post), $this->bearer($member))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('item.title', 'River trees')
            ->assertJsonPath('comments_count', 3)
            ->assertJsonPath('comments.0.id', $commentId)
            ->assertJsonPath('comments.0.replies.0.parent_id', $commentId)
            ->assertJsonCount(2, 'comments.0.replies');

        $this->patchJson(route('api.v1.comments.update', $commentId), [
            'content' => 'Updated comment text.',
        ], $this->bearer($stranger))
            ->assertForbidden();

        $this->patchJson(route('api.v1.comments.update', $commentId), [
            'content' => 'Updated comment text.',
        ], $this->bearer($member))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Your comment has been updated.')
            ->assertJsonPath('comment.content', 'Updated comment text.');

        $this->deleteJson(route('api.v1.comments.destroy', $commentId), [], $this->bearer($stranger))
            ->assertForbidden();

        $this->assertNotNull(Comment::query()->find($commentId));

        $this->deleteJson(route('api.v1.comments.destroy', $reply->json('comment.id')), [], $this->bearer($author))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'The comment has been deleted.');

        $this->deleteJson(route('api.v1.comments.destroy', $commentId), [], $this->bearer($member))
            ->assertOk()
            ->assertJsonPath('comments_count', 0);
    }

    public function test_a_member_can_comment_on_an_alert_and_the_owner_is_notified(): void
    {
        Event::fake([AlertEngagementUpdated::class]);

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $alert = Alert::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Oil on the bank',
        ]);

        $this->postJson(route('api.v1.alerts.comments.store', $alert), [
            'content' => 'I can see the spill from the bridge.',
        ], $this->bearer($member))
            ->assertCreated()
            ->assertJsonPath('message', 'Your comment has been added.')
            ->assertJsonPath('comments_count', 1);

        $this->assertSame('alert_commented', $owner->notifications()->first()?->data['kind']);

        Event::assertDispatched(AlertEngagementUpdated::class, function (AlertEngagementUpdated $event) use ($alert): bool {
            return $event->alertId === $alert->id && $event->commentsCount === 1;
        });

        $this->getJson(route('api.v1.alerts.comments.index', $alert), $this->bearer($member))
            ->assertOk()
            ->assertJsonPath('item.title', 'Oil on the bank')
            ->assertJsonPath('comments.0.content', 'I can see the spill from the bridge.');
    }

    public function test_unpublished_posts_cannot_be_commented_on_and_replies_must_match_the_story(): void
    {
        $member = User::factory()->create();
        $pending = Post::factory()->create([
            'status' => 'pending',
            'published_at' => null,
        ]);

        $this->postJson(route('api.v1.posts.comments.store', $pending), [
            'content' => 'This should not be saved.',
        ], $this->bearer($member))
            ->assertForbidden()
            ->assertJsonPath('message', 'You can only comment on published storys.');

        $post = Post::factory()->create(['status' => 'published']);
        $other = Post::factory()->create(['status' => 'published']);
        $parent = Comment::factory()->create([
            'user_id' => $member->id,
            'commentable_id' => $other->id,
            'commentable_type' => 'post',
            'content' => 'Parent comment body.',
        ]);

        $this->postJson(route('api.v1.posts.comments.store', $post), [
            'content' => 'Reply on the wrong story.',
            'parent_id' => $parent->id,
        ], $this->bearer($member))
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.parent_id.0', 'You can only reply to a comment on this story.');

        $this->assertDatabaseCount('comments', 1);
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
        ];
    }
}
