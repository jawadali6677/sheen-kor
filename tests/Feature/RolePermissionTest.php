<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Alert;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_always_creates_a_member_user(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'admin',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertSame(Role::User, User::query()->where('email', 'test@example.com')->first()?->role);
    }

    public function test_disabled_users_cannot_log_in(): void
    {
        $user = User::factory()->disabled()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_members_cannot_open_the_user_admin_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admins_can_change_a_member_role(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $member), [
                'role' => Role::Moderator->value,
                'status' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(Role::Moderator, $member->fresh()->role);
    }

    public function test_moderators_can_delete_another_users_alert(): void
    {
        $moderator = User::factory()->moderator()->create();
        $owner = User::factory()->create();
        $alert = Alert::factory()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($moderator)
            ->delete(route('alerts.destroy', $alert))
            ->assertRedirect(route('alerts.index'));

        $this->assertDatabaseMissing('alerts', [
            'id' => $alert->id,
        ]);
    }

    public function test_members_cannot_delete_another_users_story(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $owner->id,
            'category_id' => Category::query()->create([
                'name' => 'Nature',
                'slug' => 'nature-role',
                'description' => 'Nature stories',
                'status' => true,
            ])->id,
        ]);

        $this->actingAs($other)
            ->delete(route('posts.destroy', $post))
            ->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
        ]);
    }

    public function test_the_last_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $admin), [
                'role' => Role::User->value,
                'status' => '1',
            ])
            ->assertSessionHas('error');

        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }
}
