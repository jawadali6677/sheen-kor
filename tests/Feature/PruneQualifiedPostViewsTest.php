<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostView;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneQualifiedPostViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_deletes_qualified_views_older_than_thirty_five_days(): void
    {
        $this->travelTo('2026-09-27 12:00:00');

        $post = Post::factory()->create(['status' => 'published']);
        $keptBoundary = PostView::factory()->create([
            'post_id' => $post->id,
            'user_id' => $post->user_id,
            'viewer_key' => 'user:1001',
            'viewed_on' => '2026-08-23',
        ]);
        $deleted = PostView::factory()->create([
            'post_id' => $post->id,
            'user_id' => $post->user_id,
            'viewer_key' => 'user:1002',
            'viewed_on' => '2026-08-22',
        ]);
        $keptRecent = PostView::factory()->create([
            'post_id' => $post->id,
            'user_id' => $post->user_id,
            'viewer_key' => 'user:1003',
            'viewed_on' => '2026-09-27',
        ]);

        $this->artisan('post-views:prune')
            ->assertSuccessful();

        $this->assertModelExists($keptBoundary);
        $this->assertModelExists($keptRecent);
        $this->assertModelMissing($deleted);
    }

    public function test_qualified_view_pruning_is_scheduled_daily(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($scheduledEvent): bool => str_contains((string) $scheduledEvent->command, 'post-views:prune'));

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
