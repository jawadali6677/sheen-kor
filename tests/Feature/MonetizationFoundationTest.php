<?php

namespace Tests\Feature;

use App\Enums\MonetizationPackageType;
use App\Enums\Permission;
use App\Models\MonetizationPackage;
use App\Models\MonetizationSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MonetizationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_monetization_settings_table_contains_the_default_rows(): void
    {
        $this->assertTrue(Schema::hasTable('monetization_settings'));

        $this->assertSame(25, MonetizationSetting::query()->count());
        $this->assertSame('USD', monetization_setting('currency'));
        $this->assertSame('8', monetization_setting('feed_ad_interval'));
        $this->assertSame('0', monetization_setting('video_ads_enabled'));
    }

    public function test_monetization_setting_helper_returns_an_existing_value(): void
    {
        $this->assertSame('USD', monetization_setting('currency'));
    }

    public function test_monetization_setting_helper_returns_the_fallback_for_a_missing_key(): void
    {
        $this->assertSame('fallback', monetization_setting('does_not_exist', 'fallback'));
        $this->assertNull(monetization_setting('does_not_exist'));
    }

    public function test_monetization_setting_helper_casts_integers_from_the_fallback_type(): void
    {
        $this->assertSame(500, monetization_setting('eligibility_min_followers', 0));
        $this->assertSame(10, monetization_setting('eligibility_min_published_posts', 0));
        $this->assertSame(5000, monetization_setting('eligibility_min_qualified_views_30d', 0));
    }

    public function test_monetization_setting_helper_casts_booleans_from_the_fallback_type(): void
    {
        $this->assertTrue(monetization_setting('green_tick_requires_review', false));
        $this->assertFalse(monetization_setting('video_ads_enabled', true));
    }

    public function test_eligibility_defaults_are_seeded(): void
    {
        $this->assertDatabaseHas('monetization_settings', [
            'key' => 'eligibility_min_followers',
            'value' => '500',
        ]);
        $this->assertDatabaseHas('monetization_settings', [
            'key' => 'eligibility_min_published_posts',
            'value' => '10',
        ]);
        $this->assertDatabaseHas('monetization_settings', [
            'key' => 'eligibility_min_qualified_views_30d',
            'value' => '5000',
        ]);
    }

    public function test_placeholder_packages_are_seeded_disabled_at_zero_price(): void
    {
        $this->assertTrue(Schema::hasTable('monetization_packages'));
        $this->assertSame(7, MonetizationPackage::query()->count());

        foreach ([
            'green_tick_monthly',
            'green_tick_yearly',
            'post_boost_1d',
            'post_boost_7d',
            'listing_featured_7d',
            'listing_category_7d',
            'listing_boost_7d',
        ] as $slug) {
            $package = MonetizationPackage::query()->where('slug', $slug)->first();

            $this->assertNotNull($package);
            $this->assertFalse($package->is_enabled);
            $this->assertSame('0.00', $package->price);
            $this->assertSame('USD', $package->currency);
        }

        $this->assertSame(30, MonetizationPackage::query()->where('slug', 'green_tick_monthly')->value('duration_days'));
        $this->assertSame(365, MonetizationPackage::query()->where('slug', 'green_tick_yearly')->value('duration_days'));
        $this->assertSame(1, MonetizationPackage::query()->where('slug', 'post_boost_1d')->value('duration_days'));
        $this->assertSame(7, MonetizationPackage::query()->where('slug', 'post_boost_7d')->value('duration_days'));
        $this->assertSame(7, MonetizationPackage::query()->where('slug', 'listing_featured_7d')->value('duration_days'));
        $this->assertSame('featured_home', MonetizationPackage::query()->where('slug', 'listing_featured_7d')->value('placement'));
        $this->assertSame('top_of_category', MonetizationPackage::query()->where('slug', 'listing_category_7d')->value('placement'));
        $this->assertSame('boost_rank', MonetizationPackage::query()->where('slug', 'listing_boost_7d')->value('placement'));
    }

    public function test_listing_packages_are_seeded_and_rewarded_package_type_has_no_rows(): void
    {
        $this->assertSame(MonetizationPackageType::ListingPromotion, MonetizationPackageType::from('listing_promotion'));
        $this->assertSame(MonetizationPackageType::RewardedBoost, MonetizationPackageType::from('rewarded_boost'));

        $this->assertSame(3, MonetizationPackage::query()->where('type', MonetizationPackageType::ListingPromotion)->count());
        $this->assertSame(0, MonetizationPackage::query()->where('type', MonetizationPackageType::RewardedBoost)->count());
    }

    public function test_only_admins_have_the_monetization_manage_permission(): void
    {
        $this->assertSame('monetization.manage', Permission::ManageMonetization->value);

        $adminRoleId = Role::query()->where('slug', Role::ADMIN)->value('id');

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $adminRoleId,
            'permission' => Permission::ManageMonetization->value,
        ]);

        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();
        $member = User::factory()->create();

        $this->assertTrue($admin->hasPermission(Permission::ManageMonetization));
        $this->assertTrue($admin->hasPermission(Permission::ManageUsers));
        $this->assertTrue($admin->hasPermission(Permission::ModeratePosts));
        $this->assertFalse($moderator->hasPermission(Permission::ManageMonetization));
        $this->assertTrue($moderator->hasPermission(Permission::ModeratePosts));
        $this->assertFalse($member->hasPermission(Permission::ManageMonetization));
        $this->assertFalse($member->hasPermission(Permission::ManageUsers));
    }
}
