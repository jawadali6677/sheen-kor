<?php

namespace Tests\Feature;

use App\Enums\ScoreReason;
use App\Models\Alert;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserScoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_story_awards_points(): void
    {
        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Nature',
            'slug' => 'nature',
            'description' => 'Nature stories',
            'status' => true,
        ]);

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'title' => 'A walk along the river bank',
                'category_id' => $category->id,
                'content' => str_repeat('Green hills and clean water. ', 4),
            ])
            ->assertRedirect(route('posts.index'));

        $this->assertSame(10, $user->fresh()->score);
        $this->assertDatabaseHas('score_events', [
            'user_id' => $user->id,
            'reason' => ScoreReason::PostCreated->value,
            'points' => 10,
        ]);
    }

    public function test_creating_and_fixing_an_alert_awards_points(): void
    {
        Storage::fake('public');

        $reporter = User::factory()->create();
        $fixer = User::factory()->create();

        $this->actingAs($reporter)
            ->post(route('alerts.store'), [
                'title' => 'Smoke from burning waste',
                'description' => 'Thick smoke is coming from a pile of burning plastic near the forest edge.',
                'location_name' => 'North forest road',
                'severity' => 'high',
                'featured_image' => UploadedFile::fake()->image('dump.jpg'),
            ])
            ->assertRedirect();

        $this->assertSame(15, $reporter->fresh()->score);

        $alert = Alert::query()->firstOrFail();

        $this->actingAs($fixer)
            ->from(route('alerts.show', $alert))
            ->post(route('alerts.take-action', $alert))
            ->assertRedirect();

        $this->actingAs($fixer)
            ->from(route('alerts.show', $alert))
            ->post(route('alerts.mark-fixed', $alert))
            ->assertRedirect();

        $this->assertSame(25, $fixer->fresh()->score);
        $this->assertSame(15, $reporter->fresh()->score);
    }

    public function test_deleting_a_story_revokes_its_points(): void
    {
        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Nature',
            'slug' => 'nature-delete',
            'description' => 'Nature stories',
            'status' => true,
        ]);

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'title' => 'A walk along the river bank',
                'category_id' => $category->id,
                'content' => str_repeat('Green hills and clean water. ', 4),
            ]);

        $post = Post::query()->firstOrFail();

        $this->actingAs($user)
            ->delete(route('posts.destroy', $post))
            ->assertRedirect(route('posts.index'));

        $this->assertSame(0, $user->fresh()->score);
        $this->assertDatabaseCount('score_events', 0);
    }
}
