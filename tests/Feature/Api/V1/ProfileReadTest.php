<?php

namespace Tests\Feature\Api\V1;

use App\Models\Alert;
use App\Models\MarketListing;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_can_read_a_public_profile_and_its_published_lists(): void
    {
        $member = User::factory()->create([
            'name' => 'Nali Ahmed',
            'username' => 'nali',
            'score' => 30,
        ]);
        Post::factory()->create([
            'user_id' => $member->id,
            'title' => 'Published story',
            'status' => 'published',
        ]);
        Post::factory()->create([
            'user_id' => $member->id,
            'title' => 'Draft story',
            'status' => 'pending',
            'published_at' => null,
        ]);
        Alert::factory()->create([
            'user_id' => $member->id,
            'title' => 'Open alert',
        ]);
        $claimed = Alert::factory()->create([
            'title' => 'Claimed alert',
            'status' => 'in_progress',
            'action_user_id' => $member->id,
        ]);
        Alert::factory()->create([
            'title' => 'Fixed alert',
            'status' => 'fixed',
            'action_user_id' => $member->id,
        ]);
        MarketListing::factory()->create([
            'user_id' => $member->id,
            'title' => 'Published stool',
        ]);
        MarketListing::factory()->pending()->create([
            'user_id' => $member->id,
            'title' => 'Pending stool',
        ]);

        $this->getJson(route('api.v1.users.show', $member))
            ->assertOk()
            ->assertJsonPath('data.username', 'nali')
            ->assertJsonPath('data.score', 30)
            ->assertJsonPath('data.stories_count', 1)
            ->assertJsonPath('data.alerts_count', 1)
            ->assertJsonPath('data.fixes_count', 1)
            ->assertJsonPath('data.listings_count', 1)
            ->assertJsonMissingPath('data.email');

        $this->getJson(route('api.v1.users.stories', $member))
            ->assertOk()
            ->assertJsonPath('meta.per_page', 18)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Published story');

        $this->getJson(route('api.v1.users.alerts', $member))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Open alert');

        $this->getJson(route('api.v1.users.fixes', $member))
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->getJson(route('api.v1.users.listings', $member))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Published stool');

        $this->assertNotNull($claimed->id);
    }

    public function test_a_disabled_profile_is_hidden_from_guests(): void
    {
        $member = User::factory()->disabled()->create(['username' => 'hidden']);

        $this->getJson(route('api.v1.users.show', $member))->assertNotFound();
        $this->getJson(route('api.v1.users.stories', $member))->assertNotFound();
    }

    public function test_leaderboard_requires_a_token_and_lists_active_members_by_score(): void
    {
        $this->getJson(route('api.v1.leaderboard.index'))->assertUnauthorized();

        $low = User::factory()->create(['name' => 'Low score', 'score' => 5]);
        $high = User::factory()->create(['name' => 'High score', 'score' => 40]);
        User::factory()->disabled()->create(['name' => 'Disabled score', 'score' => 100]);
        $token = $low->createToken('Pixel 8', ['mobile'])->plainTextToken;

        $this->getJson(route('api.v1.leaderboard.index'), [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('data.0.username', $high->username)
            ->assertJsonPath('data.0.score', 40)
            ->assertJsonMissing(['name' => 'Disabled score']);
    }
}
