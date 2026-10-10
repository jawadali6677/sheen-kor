<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_update_their_profile_and_an_email_change_clears_verification(): void
    {
        $user = User::factory()->create([
            'name' => 'Nali Ahmed',
            'username' => 'nali',
            'email' => 'nali@example.com',
        ]);
        $this->assertNotNull($user->email_verified_at);

        $this->patchJson(route('api.v1.profile.update'), [
            'name' => 'Nali Ahmed',
            'username' => 'Nali.Ahmed',
            'email' => 'nali.new@example.com',
            'bio' => '  River keeper  ',
            'location' => ' Erbil ',
            'website' => ' https://example.com ',
        ], $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('message', 'Profile updated.')
            ->assertJsonPath('data.name', 'Nali Ahmed')
            ->assertJsonPath('data.username', 'nali.ahmed')
            ->assertJsonPath('data.email', 'nali.new@example.com')
            ->assertJsonPath('data.email_verified_at', null)
            ->assertJsonPath('data.bio', 'River keeper')
            ->assertJsonPath('data.location', 'Erbil')
            ->assertJsonPath('data.website', 'https://example.com');

        $user->refresh();
        $this->assertSame('nali.new@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('nali.ahmed', $user->username);
    }

    public function test_a_member_can_upload_an_avatar_with_method_spoofing(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'email' => 'nali@example.com',
            'profile_image' => null,
        ]);

        $response = $this->post(route('api.v1.profile.update'), [
            '_method' => 'PATCH',
            'name' => 'Nali Ahmed',
            'email' => 'nali@example.com',
            'profile_image' => UploadedFile::fake()->image('avatar.jpg'),
        ], $this->bearer($user));

        $response->assertOk()
            ->assertJsonPath('data.email_verified_at', $user->email_verified_at?->toIso8601String());

        $user->refresh();
        $this->assertNotNull($user->profile_image);
        $this->assertNotNull($user->email_verified_at);
        Storage::disk('public')->assertExists($user->profile_image);
        $this->assertStringContainsString($user->profile_image, (string) $response->json('data.avatar_url'));
    }

    public function test_profile_rules_reject_a_duplicate_email(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'taken@example.com']);

        $this->patchJson(route('api.v1.profile.update'), [
            'name' => $user->name,
            'email' => $other->email,
        ], $this->bearer($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
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
