<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_users_can_complete_their_public_profile(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'bio' => null,
            'location' => null,
            'website' => null,
            'username' => null,
        ]);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'River Keeper',
                'email' => $user->email,
                'username' => 'river.keeper',
                'bio' => 'I report dumping and help clean river banks.',
                'location' => 'Erbil',
                'website' => 'https://example.com',
                'profile_image' => UploadedFile::fake()->image('avatar.jpg'),
                'cover_image' => UploadedFile::fake()->image('cover.jpg'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('River Keeper', $user->name);
        $this->assertSame('river.keeper', $user->username);
        $this->assertSame('Erbil', $user->location);
        $this->assertSame('https://example.com', $user->website);
        $this->assertNotNull($user->profile_image);
        $this->assertNotNull($user->cover_image);
        Storage::disk('public')->assertExists($user->profile_image);
    }

    public function test_clearing_username_keeps_the_existing_public_profile_url(): void
    {
        $user = User::factory()->create([
            'username' => 'river.keeper',
        ]);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'username' => '',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('river.keeper', $user->username);
        $this->get(route('users.show', $user))
            ->assertOk()
            ->assertSee('@river.keeper');
    }

    public function test_public_profile_urls_use_username_not_numeric_ids(): void
    {
        $profile = User::factory()->create([
            'username' => 'public.person',
            'name' => 'Public Person',
        ]);

        $this->assertSame(url('/users/public.person'), route('users.show', $profile));
        $this->assertSame(url('/authors/public.person'), route('authors.show', $profile));
        $this->assertSame(url('/users/'.$profile->id.'/follow'), route('users.follow.store', $profile));
        $this->assertSame(url('/admin/users/'.$profile->id), route('admin.users.update', $profile));

        $this->get(route('users.show', $profile))
            ->assertOk()
            ->assertSee('Public Person');
        $this->get('/users/'.$profile->id)->assertNotFound();
        $this->get('/authors/'.$profile->id)->assertNotFound();
    }

    public function test_existing_users_without_a_username_get_one_when_loaded(): void
    {
        $user = User::factory()->create([
            'name' => 'Legacy Member',
            'username' => 'legacy.member',
        ]);

        User::query()->whereKey($user->id)->update(['username' => null]);

        $user = User::query()->findOrFail($user->id);
        $user->ensureHasUsername();
        $user->save();

        $this->assertNotEmpty($user->username);
        $this->actingAs($user)
            ->get(route('posts.index'))
            ->assertOk();
        $this->get(route('users.show', $user))
            ->assertOk()
            ->assertSee('Legacy Member');
    }

    public function test_other_users_can_view_a_public_profile_without_seeing_email(): void
    {
        $profile = User::factory()->create([
            'name' => 'Public Person',
            'email' => 'secret-profile@example.com',
            'username' => 'public.person',
            'bio' => 'I plant trees in the city.',
            'score' => 40,
        ]);
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('users.show', $profile))
            ->assertOk()
            ->assertSee('Public Person')
            ->assertSee('public.person')
            ->assertSee('I plant trees in the city.')
            ->assertSee('40')
            ->assertDontSee('secret-profile@example.com');
    }

    public function test_disabled_profiles_are_hidden_from_other_users(): void
    {
        $profile = User::factory()->disabled()->create();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('users.show', $profile))
            ->assertNotFound();
    }

    public function test_fixes_tab_lists_claimed_in_progress_and_fixed_alerts(): void
    {
        $profile = User::factory()->create();
        $reporter = User::factory()->create();
        $takenAt = now()->subDays(3);
        $fixedAt = now()->subDay();

        Alert::factory()->create([
            'user_id' => $profile->id,
            'title' => 'Created Only Dump',
            'location_name' => 'Created Only Street',
            'status' => 'open',
        ]);
        Alert::factory()->create([
            'user_id' => $reporter->id,
            'action_user_id' => $profile->id,
            'title' => 'River Cleanup In Progress',
            'location_name' => 'North river bank',
            'status' => 'in_progress',
            'action_taken_at' => $takenAt,
        ]);
        Alert::factory()->create([
            'user_id' => $reporter->id,
            'action_user_id' => $profile->id,
            'title' => 'Park Restored',
            'location_name' => 'Central park path',
            'status' => 'fixed',
            'action_taken_at' => $takenAt,
            'fixed_at' => $fixedAt,
        ]);

        $this->actingAs($profile)
            ->get(route('users.show', ['user' => $profile, 'tab' => 'fixes']))
            ->assertOk()
            ->assertSee('River Cleanup In Progress')
            ->assertSee('In progress')
            ->assertSee('North river bank')
            ->assertSee($takenAt->format('M d, Y'))
            ->assertSee('Park Restored')
            ->assertSee('Resolved')
            ->assertSee('Central park path')
            ->assertSee($fixedAt->format('M d, Y'))
            ->assertDontSee('Created Only Dump')
            ->assertDontSee('Created Only Street');
    }

    public function test_posts_and_alerts_tabs_do_not_include_claimed_only_alerts(): void
    {
        $profile = User::factory()->create();
        $reporter = User::factory()->create();

        Post::factory()->create([
            'user_id' => $profile->id,
            'title' => 'Neighborhood Tree Walk',
            'status' => 'published',
        ]);
        Alert::factory()->create([
            'user_id' => $profile->id,
            'title' => 'Created Only Dump',
            'status' => 'open',
        ]);
        Alert::factory()->create([
            'user_id' => $reporter->id,
            'action_user_id' => $profile->id,
            'title' => 'Claimed Only Cleanup',
            'status' => 'in_progress',
            'action_taken_at' => now(),
        ]);
        Alert::factory()->create([
            'user_id' => $reporter->id,
            'action_user_id' => $profile->id,
            'title' => 'Claimed Only Restore',
            'status' => 'fixed',
            'action_taken_at' => now()->subDay(),
            'fixed_at' => now(),
        ]);

        $this->actingAs($profile)
            ->get(route('users.show', ['user' => $profile, 'tab' => 'stories']))
            ->assertOk()
            ->assertSee('Neighborhood Tree Walk')
            ->assertDontSee('Claimed Only Cleanup')
            ->assertDontSee('Claimed Only Restore')
            ->assertDontSee('Created Only Dump');

        $this->actingAs($profile)
            ->get(route('users.show', ['user' => $profile, 'tab' => 'alerts']))
            ->assertOk()
            ->assertSee('Created Only Dump')
            ->assertDontSee('Claimed Only Cleanup')
            ->assertDontSee('Claimed Only Restore')
            ->assertDontSee('Neighborhood Tree Walk');

        $this->actingAs($profile)
            ->get(route('users.show', ['user' => $profile, 'tab' => 'fixes']))
            ->assertOk()
            ->assertViewHas('profile', function (User $user): bool {
                return $user->fixes_count === 1;
            })
            ->assertSee('Claimed Only Cleanup')
            ->assertSee('Claimed Only Restore')
            ->assertDontSee('Created Only Dump');
    }

    public function test_own_profile_uses_a_pencil_and_other_profiles_keep_follow(): void
    {
        $owner = User::factory()->create([
            'name' => 'Owner Person',
            'username' => 'owner.person',
        ]);
        $visitor = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('users.show', $owner))
            ->assertOk()
            ->assertSee('aria-label="Edit profile"', false)
            ->assertSee('profile-edit-icon', false)
            ->assertDontSee('btn-primary mb-2 text-sm', false);

        $this->actingAs($visitor)
            ->get(route('users.show', $owner))
            ->assertOk()
            ->assertSee('Follow')
            ->assertDontSee('aria-label="Edit profile"', false);
    }

    public function test_edit_profile_shows_separate_image_controls_and_the_server_limits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Change cover')
            ->assertSee('Change photo')
            ->assertSee('profile-edit-cover', false)
            ->assertSee('profile-edit-preview', false)
            ->assertSee('data-max-kilobytes="5120"', false)
            ->assertSee('data-allowed-extensions="jpg,jpeg,png,webp"', false)
            ->assertSee('The current cover stays until you choose a new one.');
    }

    public function test_profile_images_stay_when_they_are_not_replaced_and_can_be_removed(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Storage::disk('public')->put('profiles/avatars/kept.jpg', 'avatar');
        Storage::disk('public')->put('profiles/covers/kept.jpg', 'cover');
        $user->forceFill([
            'profile_image' => 'profiles/avatars/kept.jpg',
            'cover_image' => 'profiles/covers/kept.jpg',
        ])->save();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'River Keeper',
                'email' => $user->email,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('profiles/avatars/kept.jpg', $user->profile_image);
        $this->assertSame('profiles/covers/kept.jpg', $user->cover_image);
        Storage::disk('public')->assertExists('profiles/avatars/kept.jpg');
        Storage::disk('public')->assertExists('profiles/covers/kept.jpg');

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'River Keeper',
                'email' => $user->email,
                'remove_profile_image' => '1',
                'remove_cover_image' => '1',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertNull($user->profile_image);
        $this->assertNull($user->cover_image);
        Storage::disk('public')->assertMissing('profiles/avatars/kept.jpg');
        Storage::disk('public')->assertMissing('profiles/covers/kept.jpg');
    }

    public function test_profile_images_must_match_the_type_and_size_rules(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'profile_image' => 'profiles/avatars/kept.jpg',
            'cover_image' => 'profiles/covers/kept.jpg',
        ]);
        Storage::disk('public')->put('profiles/avatars/kept.jpg', 'avatar');
        Storage::disk('public')->put('profiles/covers/kept.jpg', 'cover');

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'profile_image' => UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'),
                'cover_image' => UploadedFile::fake()->image('cover.jpg')->size(5121),
            ])
            ->assertRedirect('/profile')
            ->assertSessionHasErrors([
                'profile_image' => 'The profile image field must be an image.',
                'cover_image' => 'The cover image field must not be greater than 5120 kilobytes.',
            ]);

        $user->refresh();
        $this->assertSame('profiles/avatars/kept.jpg', $user->profile_image);
        $this->assertSame('profiles/covers/kept.jpg', $user->cover_image);
    }
}
