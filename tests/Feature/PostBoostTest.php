<?php

namespace Tests\Feature;

use App\Enums\MonetizationPackageType;
use App\Enums\PostBoostSource;
use App\Enums\PostBoostStatus;
use App\Models\MonetizationPackage;
use App\Models\MonetizationSetting;
use App\Models\Post;
use App\Models\PostBoost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PostBoostTest extends TestCase
{
    use RefreshDatabase;

    public function test_owners_can_see_enabled_boost_packages_for_a_published_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id, 'status' => 'published']);
        $this->enableBoostPackages();

        $this->actingAs($user)
            ->get(route('posts.boost.create', $post))
            ->assertOk()
            ->assertSee('Post Boost - 1 Day')
            ->assertSee('Post Boost - 7 Days')
            ->assertSee('Confirm pending boost');
    }

    public function test_disabled_packages_cannot_be_selected(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = MonetizationPackage::query()->where('slug', 'post_boost_7d')->firstOrFail();

        $this->actingAs($user)
            ->from(route('posts.boost.create', $post))
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id])
            ->assertSessionHasErrors('package_id');

        $this->assertDatabaseCount('post_boosts', 0);
    }

    public function test_members_cannot_boost_another_users_post(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');

        $this->actingAs($other)
            ->get(route('posts.boost.create', $post))
            ->assertForbidden();

        $this->actingAs($other)
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id])
            ->assertForbidden();

        $this->assertDatabaseCount('post_boosts', 0);
    }

    public function test_unpublished_pending_and_rejected_posts_cannot_be_boosted(): void
    {
        $user = User::factory()->create();
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');

        foreach (['draft', 'pending', 'rejected'] as $status) {
            $post = Post::factory()->create([
                'user_id' => $user->id,
                'status' => $status,
            ]);

            $this->actingAs($user)
                ->post(route('posts.boost.store', $post), ['package_id' => $package->id])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('post_boosts', 0);
    }

    public function test_confirming_a_boost_creates_a_pending_record_using_the_package_price(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');
        $package->forceFill(['price' => '4.50'])->save();

        $this->actingAs($user)
            ->from(route('posts.boost.create', $post))
            ->post(route('posts.boost.store', $post), [
                'package_id' => $package->id,
                'price' => '99.00',
                'duration_days' => 90,
                'status' => PostBoostStatus::Active->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('post_boosts', [
            'user_id' => $user->id,
            'post_id' => $post->id,
            'package_id' => $package->id,
            'status' => PostBoostStatus::Pending->value,
            'source' => PostBoostSource::Request->value,
            'package_slug' => 'post_boost_1d',
            'price' => '4.50',
            'currency' => 'USD',
            'duration_days' => 1,
        ]);

        $this->assertFalse($post->fresh()->hasActiveBoost());
    }

    public function test_price_and_duration_snapshots_stay_unchanged_after_package_edits(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');
        $package->forceFill(['price' => '4.50', 'duration_days' => 1])->save();

        $this->actingAs($user)
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id]);

        $package->forceFill(['price' => '99.00', 'duration_days' => 7])->save();

        $boost = PostBoost::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.monetization.boosts.activate', $boost))
            ->assertRedirect(route('admin.monetization.boosts.index'));

        $boost->refresh();

        $this->assertTrue($boost->isCurrentlyActive());
        $this->assertSame('4.50', $boost->price);
        $this->assertSame(1, $boost->duration_days);
        $this->assertSame('99.00', $package->fresh()->price);
        $this->assertSame(1, (int) $boost->starts_at->diffInDays($boost->ends_at));
        $this->assertTrue($post->fresh()->hasActiveBoost());
    }

    public function test_overlapping_pending_or_active_boosts_are_rejected(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_1d');

        $this->actingAs($user)
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id])
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->from(route('posts.boost.create', $post))
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id])
            ->assertSessionHas('error');

        $this->assertSame(1, PostBoost::query()->where('post_id', $post->id)->count());
    }

    public function test_expired_boosts_are_not_treated_as_active_and_can_be_replaced(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = $this->enableBoostPackages()->firstWhere('slug', 'post_boost_7d');

        PostBoost::factory()->active()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'package_id' => $package->id,
            'status' => PostBoostStatus::Active,
            'starts_at' => now()->subDays(8),
            'ends_at' => now()->subDay(),
            'duration_days' => 7,
        ]);

        $this->assertFalse($post->fresh()->hasActiveBoost());
        $this->assertFalse(Post::query()->boosted()->whereKey($post->id)->exists());
        $this->assertSame(PostBoostStatus::Expired, PostBoost::query()->first()->displayStatus());

        $this->actingAs($user)
            ->post(route('posts.boost.store', $post), ['package_id' => $package->id])
            ->assertSessionHas('success');

        $this->assertSame(2, PostBoost::query()->where('post_id', $post->id)->count());
    }

    public function test_currently_active_scope_requires_status_and_future_end_date(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);

        PostBoost::factory()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'status' => PostBoostStatus::Pending,
        ]);

        $expired = PostBoost::factory()->active()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subMinute(),
        ]);

        $active = PostBoost::factory()->active()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        $this->assertSame([$active->id], PostBoost::query()->currentlyActive()->pluck('id')->all());
        $this->assertFalse($expired->fresh()->isCurrentlyActive());
        $this->assertTrue(Post::query()->boosted()->whereKey($post->id)->exists());
    }

    public function test_owners_can_cancel_a_pending_boost(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $boost = PostBoost::factory()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        $this->actingAs($user)
            ->delete(route('posts.boost.destroy', $boost))
            ->assertRedirect(route('posts.show', $post));

        $this->assertSame(PostBoostStatus::Cancelled, $boost->fresh()->status);
    }

    public function test_members_cannot_cancel_another_users_pending_boost(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $owner->id]);
        $boost = PostBoost::factory()->create([
            'user_id' => $owner->id,
            'post_id' => $post->id,
        ]);

        $this->actingAs($other)
            ->delete(route('posts.boost.destroy', $boost))
            ->assertForbidden();

        $this->assertSame(PostBoostStatus::Pending, $boost->fresh()->status);
    }

    public function test_admins_can_list_filter_and_cancel_boosts(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id, 'title' => 'River cleanup story']);
        $boost = PostBoost::factory()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'package_name' => 'Post Boost - 1 Day',
            'price' => '4.50',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.monetization.boosts.index'))
            ->assertOk()
            ->assertSee('River cleanup story')
            ->assertSee('4.50');

        $this->actingAs($admin)
            ->get(route('admin.monetization.boosts.show', $boost))
            ->assertOk()
            ->assertSee('Activate for testing');

        $this->actingAs($admin)
            ->post(route('admin.monetization.boosts.cancel', $boost))
            ->assertRedirect(route('admin.monetization.boosts.index'));

        $this->assertSame(PostBoostStatus::Cancelled, $boost->fresh()->status);
    }

    public function test_admins_can_grant_a_boost_when_unpaid_grants_are_enabled(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = MonetizationPackage::query()->where('slug', 'post_boost_7d')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.monetization.boosts.grant'), [
                'post_id' => $post->id,
                'package_id' => $package->id,
            ])
            ->assertRedirect(route('admin.monetization.boosts.index'));

        $boost = PostBoost::query()->firstOrFail();

        $this->assertTrue($post->fresh()->hasActiveBoost());
        $this->assertSame(PostBoostSource::AdminGrant, $boost->source);
        $this->assertSame('post_boost_7d', $boost->package_slug);
        $this->assertSame(7, $boost->duration_days);
        $this->assertSame($admin->id, $boost->activated_by);
    }

    public function test_unpaid_boost_grants_are_blocked_when_the_setting_is_disabled(): void
    {
        MonetizationSetting::query()->where('key', 'admin_unpaid_grants_enabled')->update(['value' => '0']);

        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create();
        $package = MonetizationPackage::query()->where('slug', 'post_boost_1d')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.monetization.boosts.index'))
            ->post(route('admin.monetization.boosts.grant'), [
                'post_id' => $post->id,
                'package_id' => $package->id,
            ])
            ->assertRedirect(route('admin.monetization.boosts.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('post_boosts', 0);
    }

    public function test_members_cannot_open_the_boost_admin_queue(): void
    {
        $member = User::factory()->create();
        $boost = PostBoost::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.monetization.boosts.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('admin.monetization.boosts.activate', $boost))
            ->assertForbidden();
    }

    public function test_published_posts_show_a_boosted_label_when_active(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Visible boosted story',
        ]);

        PostBoost::factory()->active()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);

        $this->actingAs($user)
            ->get(route('posts.show', $post))
            ->assertOk()
            ->assertSee('Boosted')
            ->assertSee('Boost Post');

        $this->get(route('posts.index'))
            ->assertOk()
            ->assertSee('Boosted');
    }

    /**
     * @return Collection<int, MonetizationPackage>
     */
    private function enableBoostPackages()
    {
        MonetizationPackage::query()
            ->where('type', MonetizationPackageType::PostBoost)
            ->update(['is_enabled' => true]);

        return MonetizationPackage::query()
            ->where('type', MonetizationPackageType::PostBoost)
            ->get();
    }
}
