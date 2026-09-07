<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShortVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_attach_a_short_video_to_a_story(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Nature',
            'slug' => 'nature-video',
            'description' => 'Nature stories',
            'status' => true,
        ]);

        $this->actingAs($user)
            ->post(route('posts.store'), [
                'title' => 'Tree planting after the rain',
                'category_id' => $category->id,
                'content' => str_repeat('We planted saplings along the trail. ', 4),
                'videos' => [$this->shortVideo()],
            ])
            ->assertRedirect(route('posts.index'));

        $post = Post::query()->where('title', 'Tree planting after the rain')->first();

        $this->assertNotNull($post);
        $this->assertSame(1, $post->images()->where('media_type', 'video')->count());
        Storage::disk('public')->assertExists($post->images()->first()->image);
    }

    public function test_users_can_attach_a_short_video_to_an_alert(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('alerts.store'), [
                'title' => 'Dumping beside the orchard',
                'description' => 'Bags of waste were left next to newly planted trees.',
                'location_name' => 'East orchard path',
                'severity' => 'medium',
                'featured_image' => UploadedFile::fake()->image('dump.jpg'),
                'videos' => [$this->shortVideo()],
            ])
            ->assertRedirect();

        $alert = Alert::query()->where('title', 'Dumping beside the orchard')->first();

        $this->assertNotNull($alert);
        $this->assertSame(1, $alert->reportImages()->where('media_type', 'video')->count());
        $this->assertSame('report', $alert->reportImages()->first()->kind);
    }

    public function test_users_can_attach_a_short_video_when_marking_an_alert_fixed(): void
    {
        Storage::fake('public');

        $helper = User::factory()->create();
        $alert = Alert::factory()->create([
            'status' => 'in_progress',
            'action_user_id' => $helper->id,
            'action_taken_at' => now(),
        ]);

        $this->actingAs($helper)
            ->from(route('alerts.show', $alert))
            ->post(route('alerts.mark-fixed', $alert), [
                'fix_videos' => [$this->shortVideo()],
            ])
            ->assertRedirect(route('alerts.show', $alert));

        $this->assertSame(1, $alert->fixImages()->where('media_type', 'video')->count());
        $this->assertSame('fixed', $alert->fresh()->status);
    }

    public function test_a_photo_cannot_be_uploaded_as_a_short_video(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $category = Category::query()->create([
            'name' => 'Nature',
            'slug' => 'nature-video-invalid',
            'description' => 'Nature stories',
            'status' => true,
        ]);

        $this->actingAs($user)
            ->from(route('posts.create'))
            ->followingRedirects()
            ->post(route('posts.store'), [
                'title' => 'This should not save a fake clip',
                'category_id' => $category->id,
                'content' => str_repeat('Green hills and clean water. ', 4),
                'videos' => [UploadedFile::fake()->image('not-a-video.jpg')],
            ])
            ->assertOk()
            ->assertSee('must be a file of type', false);

        $this->assertSame(0, Post::query()->count());
    }

    private function shortVideo(): UploadedFile
    {
        return UploadedFile::fake()->create('clip.mp4', 400, 'video/mp4');
    }
}
