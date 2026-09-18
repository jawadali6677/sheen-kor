<?php

namespace Tests\Feature;

use App\Enums\MonetizationPackageType;
use App\Models\MonetizationPackage;
use App\Models\MonetizationSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMonetizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_monetization_admin_page(): void
    {
        $this->get(route('admin.monetization.index'))
            ->assertRedirect(route('login'));
    }

    public function test_members_cannot_open_the_monetization_admin_page(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.monetization.index'))
            ->assertForbidden();
    }

    public function test_moderators_cannot_open_the_monetization_admin_page(): void
    {
        $moderator = User::factory()->moderator()->create();

        $this->actingAs($moderator)
            ->get(route('admin.monetization.index'))
            ->assertForbidden();
    }

    public function test_admins_can_open_the_monetization_admin_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.monetization.index'))
            ->assertOk()
            ->assertSee('Monetization')
            ->assertSee('Dashboard')
            ->assertSee('Green Tick Monthly')
            ->assertSee('Post Boost - 7 Days')
            ->assertSee('Featured Listing - 7 Days')
            ->assertSee('Orders')
            ->assertDontSee('listing_promotion');
    }

    public function test_admins_can_update_monetization_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('admin.monetization.settings.update'), $this->validSettings([
                'currency' => 'eur',
                'feed_ad_interval' => 12,
                'green_tick_requires_review' => '0',
                'eligibility_min_followers' => 750,
            ]))
            ->assertRedirect(route('admin.monetization.index'));

        $this->assertDatabaseHas('monetization_settings', [
            'key' => 'currency',
            'value' => 'EUR',
        ]);
        $this->assertDatabaseHas('monetization_settings', [
            'key' => 'feed_ad_interval',
            'value' => '12',
        ]);
        $this->assertDatabaseHas('monetization_settings', [
            'key' => 'green_tick_requires_review',
            'value' => '0',
        ]);
        $this->assertDatabaseHas('monetization_settings', [
            'key' => 'eligibility_min_followers',
            'value' => '750',
        ]);
    }

    public function test_invalid_monetization_settings_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.monetization.index'))
            ->patch(route('admin.monetization.settings.update'), $this->validSettings([
                'currency' => 'US',
                'feed_ad_interval' => 0,
                'eligibility_min_followers' => -1,
            ]))
            ->assertRedirect(route('admin.monetization.index'))
            ->assertSessionHasErrors(['currency', 'feed_ad_interval', 'eligibility_min_followers']);

        $this->assertDatabaseHas('monetization_settings', [
            'key' => 'currency',
            'value' => 'USD',
        ]);
        $this->assertDatabaseHas('monetization_settings', [
            'key' => 'feed_ad_interval',
            'value' => '8',
        ]);
    }

    public function test_members_cannot_update_monetization_settings(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->patch(route('admin.monetization.settings.update'), $this->validSettings())
            ->assertForbidden();

        $this->assertDatabaseHas('monetization_settings', [
            'key' => 'currency',
            'value' => 'USD',
        ]);
    }

    public function test_admins_can_update_a_package_price_duration_and_status(): void
    {
        $admin = User::factory()->admin()->create();
        $package = MonetizationPackage::query()->where('slug', 'green_tick_monthly')->firstOrFail();
        $originalType = $package->type;

        $this->actingAs($admin)
            ->patch(route('admin.monetization.packages.update', $package), [
                'price' => '9.50',
                'currency' => 'usd',
                'duration_days' => 45,
                'is_enabled' => '1',
                'type' => MonetizationPackageType::PostBoost->value,
                'slug' => 'tampered-slug',
            ])
            ->assertRedirect(route('admin.monetization.index'));

        $package->refresh();

        $this->assertSame('9.50', $package->price);
        $this->assertSame('USD', $package->currency);
        $this->assertSame(45, $package->duration_days);
        $this->assertTrue($package->is_enabled);
        $this->assertSame($originalType, $package->type);
        $this->assertSame('green_tick_monthly', $package->slug);
    }

    public function test_admins_can_disable_a_package(): void
    {
        $admin = User::factory()->admin()->create();
        $package = MonetizationPackage::query()->where('slug', 'post_boost_1d')->firstOrFail();
        $package->forceFill(['is_enabled' => true])->save();

        $this->actingAs($admin)
            ->patch(route('admin.monetization.packages.update', $package), [
                'price' => $package->price,
                'currency' => $package->currency,
                'duration_days' => $package->duration_days,
                'is_enabled' => '0',
            ])
            ->assertRedirect(route('admin.monetization.index'));

        $this->assertFalse($package->fresh()->is_enabled);
    }

    public function test_invalid_package_values_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $package = MonetizationPackage::query()->where('slug', 'post_boost_7d')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.monetization.packages.edit', $package))
            ->patch(route('admin.monetization.packages.update', $package), [
                'price' => '-1',
                'currency' => 'US',
                'duration_days' => 0,
                'is_enabled' => '1',
            ])
            ->assertRedirect(route('admin.monetization.packages.edit', $package))
            ->assertSessionHasErrors(['price', 'currency', 'duration_days']);

        $package->refresh();

        $this->assertSame('0.00', $package->price);
        $this->assertSame(7, $package->duration_days);
        $this->assertFalse($package->is_enabled);
    }

    public function test_members_cannot_update_packages(): void
    {
        $member = User::factory()->create();
        $package = MonetizationPackage::query()->where('slug', 'green_tick_yearly')->firstOrFail();

        $this->actingAs($member)
            ->patch(route('admin.monetization.packages.update', $package), [
                'price' => '12.00',
                'currency' => 'USD',
                'duration_days' => 365,
                'is_enabled' => '1',
            ])
            ->assertForbidden();

        $this->assertSame('0.00', $package->fresh()->price);
        $this->assertFalse($package->fresh()->is_enabled);
    }

    public function test_members_cannot_open_the_package_edit_page(): void
    {
        $member = User::factory()->create();
        $package = MonetizationPackage::query()->where('slug', 'green_tick_monthly')->firstOrFail();

        $this->actingAs($member)
            ->get(route('admin.monetization.packages.edit', $package))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validSettings(array $overrides = []): array
    {
        $settings = MonetizationSetting::query()->pluck('value', 'key')->all();

        return array_merge($settings, $overrides);
    }
}
