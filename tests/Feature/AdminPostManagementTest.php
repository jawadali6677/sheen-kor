<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Notifications\PostModerationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPostManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_cannot_open_the_admin_posts_page(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.posts.index'))
            ->assertForbidden();
    }

    public function test_moderators_can_view_pending_posts_and_counts(): void
    {
        $moderator = User::factory()->moderator()->create();
        $author = User::factory()->create(['name' => 'River Writer']);

        Post::factory()->create([
            'user_id' => $author->id,
            'title' => 'Needs a human look',
            'excerpt' => 'Cleanup day notes from the north trail.',
            'status' => 'pending',
            'published_at' => null,
        ]);
        Post::factory()->create([
            'user_id' => $author->id,
            'title' => 'Already live',
            'status' => 'published',
            'published_at' => now(),
        ]);
        Post::factory()->create([
            'user_id' => $author->id,
            'title' => 'Not a fit',
            'status' => 'rejected',
            'published_at' => null,
        ]);

        $this->actingAs($moderator)
            ->get(route('admin.posts.index'))
            ->assertOk()
            ->assertSee('Needs a human look')
            ->assertSee('River Writer')
            ->assertSee('Cleanup day notes')
            ->assertDontSee('Already live')
            ->assertSee('Pending')
            ->assertSee('Published')
            ->assertSee('Rejected');
    }

    public function test_status_tabs_and_search_filter_the_admin_post_table(): void
    {
        $admin = User::factory()->admin()->create();

        Post::factory()->create([
            'title' => 'Published river walk',
            'status' => 'published',
            'published_at' => now(),
        ]);
        Post::factory()->create([
            'title' => 'Pending park clean',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.posts.index', ['status' => 'published']))
            ->assertOk()
            ->assertSee('Published river walk')
            ->assertDontSee('Pending park clean');

        $this->actingAs($admin)
            ->get(route('admin.posts.index', ['status' => 'all', 'q' => 'park clean']))
            ->assertOk()
            ->assertSee('Pending park clean')
            ->assertDontSee('Published river walk');
    }

    public function test_admin_can_publish_a_pending_post_and_notify_the_author(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'title' => 'Saturday planting',
            'status' => 'pending',
            'published_at' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.posts.publish', $post))
            ->assertRedirect();

        $post->refresh();

        $this->assertSame('published', $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertSame(1, $author->notifications()->count());
        $this->assertSame(PostModerationResult::class, $author->notifications()->first()?->type);
        $this->assertSame('post_published', $author->notifications()->first()?->data['kind']);
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_admin_can_reject_a_pending_post_with_a_community_guidelines_notice(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'title' => 'Unclear cleanup post',
            'status' => 'pending',
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.posts.reject', $post))
            ->assertRedirect();

        $post->refresh();

        $this->assertSame('rejected', $post->status);
        $this->assertNull($post->published_at);

        $notification = $author->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('post_rejected', $notification->data['kind']);
        $this->assertSame(PostModerationResult::RejectedBody, $notification->data['body']);
        $this->assertStringContainsString('community guidelines', $notification->data['body']);
        $this->assertStringNotContainsString('Sightengine', $notification->data['body']);
        $this->assertStringNotContainsString('0.55', $notification->data['body']);
    }

    public function test_setting_a_post_pending_does_not_notify_the_author(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.posts.pending', $post))
            ->assertRedirect();

        $post->refresh();

        $this->assertSame('pending', $post->status);
        $this->assertNull($post->published_at);
        $this->assertSame(0, $author->notifications()->count());
    }

    public function test_admin_can_view_and_delete_a_post(): void
    {
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create([
            'title' => 'Story to remove',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.posts.show', $post))
            ->assertOk()
            ->assertSee('Story to remove');

        $this->actingAs($admin)
            ->delete(route('admin.posts.destroy', $post))
            ->assertRedirect(route('admin.posts.index'));

        $this->assertModelMissing($post);
    }

    public function test_pending_rows_include_view_publish_and_reject_actions(): void
    {
        $admin = User::factory()->admin()->create();
        Post::factory()->create([
            'title' => 'Needs buttons',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.posts.index'))
            ->assertOk()
            ->assertSee('Needs buttons')
            ->assertSee('Publish')
            ->assertSee('Reject')
            ->assertSee('View');
    }

    public function test_partial_index_returns_the_table_without_the_full_page_chrome(): void
    {
        $admin = User::factory()->admin()->create();
        Post::factory()->create([
            'title' => 'Partial pending story',
            'status' => 'pending',
        ]);
        Post::factory()->create([
            'title' => 'Hidden published story',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.posts.index', ['partial' => 1, 'status' => 'pending']))
            ->assertOk()
            ->assertSee('Partial pending story')
            ->assertDontSee('Hidden published story')
            ->assertDontSee('Review stories');
    }

    public function test_partial_index_paginates_filtered_results(): void
    {
        $admin = User::factory()->admin()->create();

        Post::factory()->create([
            'title' => 'Oldest pending story',
            'status' => 'pending',
        ]);
        Post::factory()->count(20)->create([
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.posts.index', ['partial' => 1, 'status' => 'pending', 'page' => 2]))
            ->assertOk()
            ->assertSee('Oldest pending story');

        $this->actingAs($admin)
            ->get(route('admin.posts.index', ['partial' => 1, 'status' => 'pending']))
            ->assertOk()
            ->assertDontSee('Oldest pending story');
    }

    public function test_json_publish_updates_the_post_without_a_redirect(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'pending',
            'published_at' => null,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.posts.publish', $post))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'The story has been published.',
            ]);

        $this->assertSame('published', $post->fresh()->status);
        $this->assertSame(1, $author->notifications()->count());
    }

    public function test_json_reject_keeps_the_community_guidelines_notice(): void
    {
        $admin = User::factory()->admin()->create();
        $author = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $author->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.posts.reject', $post))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('rejected', $post->fresh()->status);
        $this->assertSame(PostModerationResult::RejectedBody, $author->notifications()->first()?->data['body']);
    }

    public function test_members_cannot_publish_posts_over_json(): void
    {
        $member = User::factory()->create();
        $post = Post::factory()->create(['status' => 'pending']);

        $this->actingAs($member)
            ->postJson(route('admin.posts.publish', $post))
            ->assertForbidden();

        $this->assertSame('pending', $post->fresh()->status);
    }
}
