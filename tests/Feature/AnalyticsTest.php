<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_cannot_view_analytics(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('analytics.index'))
            ->assertForbidden();
    }

    public function test_admins_can_view_daily_weekly_and_monthly_reports(): void
    {
        Carbon::setTestNow('2026-09-06 12:00:00');

        $admin = User::factory()->admin()->create([
            'created_at' => now(),
        ]);
        User::factory()->disabled()->create([
            'created_at' => now(),
        ]);
        $category = Category::query()->create([
            'name' => 'Nature',
            'slug' => 'nature-analytics',
            'description' => 'Nature',
            'status' => true,
        ]);

        Post::factory()->create([
            'user_id' => $admin->id,
            'category_id' => $category->id,
            'created_at' => now(),
        ]);

        Alert::factory()->create([
            'user_id' => $admin->id,
            'status' => 'open',
            'severity' => 'high',
            'created_at' => now(),
        ]);
        Alert::factory()->create([
            'user_id' => $admin->id,
            'status' => 'in_progress',
            'action_user_id' => $admin->id,
            'action_taken_at' => now(),
            'created_at' => now()->subDay(),
        ]);
        Alert::factory()->create([
            'user_id' => $admin->id,
            'status' => 'fixed',
            'action_user_id' => $admin->id,
            'action_taken_at' => now()->subDays(2),
            'fixed_at' => now(),
            'created_at' => now()->subDays(2),
        ]);

        $this->actingAs($admin)
            ->get(route('analytics.index', ['period' => 'daily']))
            ->assertOk()
            ->assertSee('Analytics')
            ->assertSee('apexcharts', false)
            ->assertSee('activity-chart', false)
            ->assertSee('1 open')
            ->assertSee('1 in progress')
            ->assertSee('1 fixed')
            ->assertSee('Users by account status')
            ->assertSee('user-status-chart', false)
            ->assertSee('1 active')
            ->assertSee('1 disabled');

        $this->actingAs($admin)
            ->get(route('analytics.index', ['period' => 'weekly']))
            ->assertOk()
            ->assertSee('Week');

        $this->actingAs($admin)
            ->get(route('analytics.index', ['period' => 'monthly']))
            ->assertOk()
            ->assertSee(now()->format('M Y'));

        Carbon::setTestNow();
    }

    public function test_moderators_can_view_analytics(): void
    {
        $moderator = User::factory()->moderator()->create();

        $this->actingAs($moderator)
            ->get(route('analytics.index'))
            ->assertOk();
    }
}
