<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostEngagementTest extends TestCase
{
    use RefreshDatabase;

    private function publishedPost(?User $author = null): Post
    {
        return Post::factory()->create([
            'user_id' => ($author ?? User::factory()->create())->id,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function test_guests_cannot_like_or_comment(): void
    {
        $post = $this->publishedPost();

        $this->postJson(route('posts.likes.store', $post))
            ->assertUnauthorized();

        $this->postJson(route('posts.comments.store', $post), [
            'content' => 'This is a comment.',
        ])->assertUnauthorized();
    }

    public function test_user_can_like_and_unlike_a_published_post(): void
    {
        $user = User::factory()->create();
        $post = $this->publishedPost();

        $this->actingAs($user)
            ->postJson(route('posts.likes.store', $post))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'liked' => true,
                'likes_count' => 1,
            ]);

        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        $this->actingAs($user)
            ->postJson(route('posts.likes.store', $post))
            ->assertOk()
            ->assertJson([
                'liked' => true,
                'likes_count' => 1,
            ]);

        $this->assertSame(1, Like::query()->where('post_id', $post->id)->count());

        $this->actingAs($user)
            ->deleteJson(route('posts.likes.destroy', $post))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'liked' => false,
                'likes_count' => 0,
            ]);

        $this->assertDatabaseMissing('likes', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_user_cannot_like_an_unpublished_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'pending',
            'published_at' => null,
        ]);

        $this->actingAs($user)
            ->postJson(route('posts.likes.store', $post))
            ->assertForbidden()
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseCount('likes', 0);
    }

    public function test_user_can_create_update_and_delete_their_comment(): void
    {
        $user = User::factory()->create();
        $post = $this->publishedPost();

        $create = $this->actingAs($user)
            ->postJson(route('posts.comments.store', $post), [
                'content' => '<b>Great</b> story to read.',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('comment.content', 'Great story to read.')
            ->assertJsonPath('comment.can_edit', true)
            ->assertJsonPath('comments_count', 1);

        $commentId = $create->json('comment.id');

        $this->actingAs($user)
            ->putJson(route('comments.update', $commentId), [
                'content' => 'Updated comment text.',
            ])
            ->assertOk()
            ->assertJsonPath('comment.content', 'Updated comment text.');

        $this->actingAs($user)
            ->deleteJson(route('comments.destroy', $commentId))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'comments_count' => 0,
            ]);

        $this->assertDatabaseMissing('comments', [
            'id' => $commentId,
        ]);
    }

    public function test_comment_validation_rejects_short_or_missing_content(): void
    {
        $user = User::factory()->create();
        $post = $this->publishedPost();

        $this->actingAs($user)
            ->postJson(route('posts.comments.store', $post), [
                'content' => 'no',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);

        $this->actingAs($user)
            ->postJson(route('posts.comments.store', $post), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_user_cannot_update_someone_elses_comment(): void
    {
        $author = User::factory()->create();
        $other = User::factory()->create();
        $post = $this->publishedPost();

        $comment = Comment::factory()->create([
            'user_id' => $author->id,
            'post_id' => $post->id,
            'content' => 'Original comment body.',
        ]);

        $this->actingAs($other)
            ->putJson(route('comments.update', $comment), [
                'content' => 'Hijacked comment body.',
            ])
            ->assertForbidden();

        $this->assertSame('Original comment body.', $comment->fresh()->content);
    }

    public function test_post_owner_can_delete_comments_on_their_story(): void
    {
        $owner = User::factory()->create();
        $commenter = User::factory()->create();
        $post = $this->publishedPost($owner);

        $comment = Comment::factory()->create([
            'user_id' => $commenter->id,
            'post_id' => $post->id,
            'content' => 'A comment on this story.',
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('comments.destroy', $comment))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('comments', [
            'id' => $comment->id,
        ]);
    }

    public function test_user_can_reply_to_a_top_level_comment_only(): void
    {
        $user = User::factory()->create();
        $post = $this->publishedPost();

        $parent = Comment::factory()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'content' => 'Parent comment body.',
        ]);

        $reply = $this->actingAs($user)
            ->postJson(route('posts.comments.store', $post), [
                'content' => 'This is a reply.',
                'parent_id' => $parent->id,
            ])
            ->assertCreated()
            ->assertJsonPath('comment.parent_id', $parent->id);

        $this->actingAs($user)
            ->postJson(route('posts.comments.store', $post), [
                'content' => 'Nested reply is not allowed.',
                'parent_id' => $reply->json('comment.id'),
            ])
            ->assertUnprocessable();

        $otherPost = $this->publishedPost();

        $this->actingAs($user)
            ->postJson(route('posts.comments.store', $otherPost), [
                'content' => 'Reply on the wrong story.',
                'parent_id' => $parent->id,
            ])
            ->assertUnprocessable();
    }

    public function test_user_cannot_comment_on_an_unpublished_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->postJson(route('posts.comments.store', $post), [
                'content' => 'This should not be saved.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_published_post_show_page_includes_engagement_ui(): void
    {
        $user = User::factory()->create();
        $post = $this->publishedPost();

        $this->actingAs($user)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('js-like-button', false)
            ->assertSee('js-comment-button', false)
            ->assertSee('id="commentModal"', false);
    }

    public function test_comments_can_be_listed_for_the_modal(): void
    {
        $user = User::factory()->create();
        $post = $this->publishedPost();

        Comment::factory()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'content' => 'Visible in the modal thread.',
        ]);

        $this->actingAs($user)
            ->getJson(route('posts.comments.index', $post))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('comments.0.content', 'Visible in the modal thread.');
    }

    public function test_stories_index_includes_like_and_comment_buttons(): void
    {
        $user = User::factory()->create();
        $this->publishedPost();

        $this->actingAs($user)
            ->get(route('posts.index'))
            ->assertOk()
            ->assertSee('js-like-button', false)
            ->assertSee('js-comment-button', false)
            ->assertSee('id="commentModal"', false)
            ->assertSee('id="category-filter"', false)
            ->assertDontSee('Browse by author');
    }
}
