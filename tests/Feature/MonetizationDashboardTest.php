<?php

namespace Tests\Feature;

use App\Enums\AdEventType;
use App\Enums\AdPlacement;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PostBoostSource;
use App\Enums\PostBoostStatus;
use App\Models\AdEvent;
use App\Models\Advertisement;
use App\Models\MonetizationPackage;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Post;
use App\Models\PostBoost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonetizationDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_cannot_view_the_monetization_dashboard(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.monetization.dashboard'))
            ->assertForbidden();
    }

    public function test_admins_can_view_dashboard_totals_for_paid_orders_ads_and_boosts(): void
    {
        Carbon::setTestNow('2026-09-18 12:00:00');

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $package = MonetizationPackage::query()->where('slug', 'post_boost_1d')->firstOrFail();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $paidOrder = Order::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'post_id' => $post->id,
            'status' => OrderStatus::Paid,
            'amount' => '12.50',
            'currency' => 'USD',
            'snapshot' => [
                'name' => $package->name,
                'slug' => $package->slug,
                'type' => $package->type->value,
                'duration_days' => $package->duration_days,
                'placement' => null,
                'price' => '12.50',
            ],
        ]);
        Payment::factory()->create([
            'order_id' => $paidOrder->id,
            'amount' => '12.50',
            'status' => PaymentStatus::Paid,
            'updated_at' => now(),
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => OrderStatus::Pending,
            'amount' => '4.00',
        ]);

        PostBoost::factory()->active()->create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'package_id' => $package->id,
            'source' => PostBoostSource::AdminGrant,
            'status' => PostBoostStatus::Active,
            'price' => '99.00',
        ]);

        $advertisement = Advertisement::query()->where('slug', 'demo-bottles')->firstOrFail();
        AdEvent::query()->create([
            'advertisement_id' => $advertisement->id,
            'user_id' => $user->id,
            'visitor_key' => 'dash-impression',
            'type' => AdEventType::Impression,
            'placement' => AdPlacement::FeedPosts,
        ]);
        AdEvent::query()->create([
            'advertisement_id' => $advertisement->id,
            'user_id' => $user->id,
            'visitor_key' => 'dash-click',
            'type' => AdEventType::Click,
            'placement' => AdPlacement::FeedPosts,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.monetization.dashboard'))
            ->assertOk()
            ->assertSee('Monetization dashboard')
            ->assertSee('apexcharts', false)
            ->assertSee('revenue-chart', false)
            ->assertSee('12.50 USD')
            ->assertSee('1 paid orders')
            ->assertSee('1')
            ->assertSee('1 active boosts')
            ->assertSee('1 impressions')
            ->assertSee('1 clicks')
            ->assertDontSee('99.00');

        Carbon::setTestNow();
    }

    public function test_unpaid_admin_grants_do_not_count_as_revenue(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $post = Post::factory()->create(['user_id' => $user->id]);
        $package = MonetizationPackage::query()->where('slug', 'post_boost_7d')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.monetization.boosts.grant'), [
                'post_id' => $post->id,
                'package_id' => $package->id,
            ]);

        $this->assertTrue($post->fresh()->hasActiveBoost());
        $this->assertDatabaseCount('orders', 0);

        $this->actingAs($admin)
            ->get(route('admin.monetization.dashboard'))
            ->assertOk()
            ->assertSee('0.00 USD')
            ->assertSee('1 active boosts');
    }

    public function test_invalid_period_falls_back_to_daily(): void
    {
        Carbon::setTestNow('2026-09-18 12:00:00');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.monetization.dashboard', ['period' => 'yearly']))
            ->assertOk()
            ->assertSee('Monetization dashboard')
            ->assertSee(now()->format('M j'))
            ->assertDontSee('Week ');

        Carbon::setTestNow();
    }
}
