<?php

namespace Tests\Feature;

use App\Enums\MarketListingReportReason;
use App\Enums\MarketListingStatus;
use App\Models\MarketListing;
use App\Models\MarketListingReport;
use App\Models\User;
use App\Notifications\MarketListingModerationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketListingModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_cannot_open_the_admin_market_page(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.market.index'))
            ->assertForbidden();
    }

    public function test_moderators_can_view_pending_listings_and_counts(): void
    {
        $moderator = User::factory()->moderator()->create();
        $owner = User::factory()->create(['name' => 'River Seller']);

        MarketListing::factory()->pending()->create([
            'user_id' => $owner->id,
            'title' => 'Needs a human look',
            'description' => 'A cedar planter from the north trail.',
        ]);
        MarketListing::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Already live',
        ]);
        MarketListing::factory()->rejected()->create([
            'user_id' => $owner->id,
            'title' => 'Not a fit',
        ]);

        $this->actingAs($moderator)
            ->get(route('admin.market.index'))
            ->assertOk()
            ->assertSee('Needs a human look')
            ->assertSee('River Seller')
            ->assertSee('A cedar planter')
            ->assertDontSee('Already live')
            ->assertSee('Pending')
            ->assertSee('Published')
            ->assertSee('Rejected')
            ->assertSee('Reported');
    }

    public function test_status_tabs_search_and_reported_filter_the_admin_market_table(): void
    {
        $admin = User::factory()->admin()->create();

        MarketListing::factory()->create([
            'title' => 'Published river walk',
        ]);
        MarketListing::factory()->pending()->create([
            'title' => 'Pending park clean',
        ]);
        $reported = MarketListing::factory()->create([
            'title' => 'Reported watering can',
        ]);
        MarketListingReport::factory()->create([
            'market_listing_id' => $reported->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.market.index', ['status' => 'published']))
            ->assertOk()
            ->assertSee('Published river walk')
            ->assertDontSee('Pending park clean');

        $this->actingAs($admin)
            ->get(route('admin.market.index', ['status' => 'all', 'q' => 'park clean']))
            ->assertOk()
            ->assertSee('Pending park clean')
            ->assertDontSee('Published river walk');

        $this->actingAs($admin)
            ->get(route('admin.market.index', ['status' => 'reported']))
            ->assertOk()
            ->assertSee('Reported watering can')
            ->assertDontSee('Pending park clean');
    }

    public function test_admin_can_publish_a_pending_listing_and_notify_the_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $listing = MarketListing::factory()->pending()->create([
            'user_id' => $owner->id,
            'title' => 'Saturday planting pots',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.market.publish', $listing))
            ->assertRedirect();

        $listing->refresh();

        $this->assertSame(MarketListingStatus::Published, $listing->status);
        $this->assertNotNull($listing->published_at);
        $this->assertSame(1, $owner->notifications()->count());
        $this->assertSame(MarketListingModerationResult::class, $owner->notifications()->first()?->type);
        $this->assertSame('market_listing_published', $owner->notifications()->first()?->data['kind']);
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_admin_can_reject_a_pending_listing_with_a_community_guidelines_notice(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $listing = MarketListing::factory()->pending()->create([
            'user_id' => $owner->id,
            'title' => 'Unclear cleanup listing',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.market.reject', $listing))
            ->assertRedirect();

        $listing->refresh();

        $this->assertSame(MarketListingStatus::Rejected, $listing->status);
        $this->assertNull($listing->published_at);

        $notification = $owner->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('market_listing_rejected', $notification->data['kind']);
        $this->assertSame(MarketListingModerationResult::RejectedBody, $notification->data['body']);
        $this->assertStringContainsString('community guidelines', $notification->data['body']);
        $this->assertStringNotContainsString('Sightengine', $notification->data['body']);
        $this->assertStringNotContainsString('0.55', $notification->data['body']);
    }

    public function test_setting_a_listing_pending_does_not_notify_the_owner(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.market.pending', $listing))
            ->assertRedirect();

        $listing->refresh();

        $this->assertSame(MarketListingStatus::Pending, $listing->status);
        $this->assertNull($listing->published_at);
        $this->assertSame(0, $owner->notifications()->count());
    }

    public function test_admin_can_view_and_delete_a_listing(): void
    {
        $admin = User::factory()->admin()->create();
        $listing = MarketListing::factory()->pending()->create([
            'title' => 'Listing to remove',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.market.show', $listing))
            ->assertOk()
            ->assertSee('Listing to remove');

        $this->actingAs($admin)
            ->delete(route('admin.market.destroy', $listing))
            ->assertRedirect(route('admin.market.index'));

        $this->assertModelMissing($listing);
    }

    public function test_pending_rows_include_view_publish_and_reject_actions(): void
    {
        $admin = User::factory()->admin()->create();
        MarketListing::factory()->pending()->create([
            'title' => 'Needs buttons',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.market.index'))
            ->assertOk()
            ->assertSee('Needs buttons')
            ->assertSee('Publish')
            ->assertSee('Reject')
            ->assertSee('View');
    }

    public function test_partial_index_returns_the_table_without_the_full_page_chrome(): void
    {
        $admin = User::factory()->admin()->create();
        MarketListing::factory()->pending()->create([
            'title' => 'Partial pending listing',
        ]);
        MarketListing::factory()->create([
            'title' => 'Hidden published listing',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.market.index', ['partial' => 1, 'status' => 'pending']))
            ->assertOk()
            ->assertSee('Partial pending listing')
            ->assertDontSee('Hidden published listing')
            ->assertDontSee('Review market');
    }

    public function test_partial_index_paginates_filtered_results(): void
    {
        $admin = User::factory()->admin()->create();

        MarketListing::factory()->pending()->create([
            'title' => 'Oldest pending listing',
        ]);
        MarketListing::factory()->pending()->count(20)->create();

        $this->actingAs($admin)
            ->get(route('admin.market.index', ['partial' => 1, 'status' => 'pending', 'page' => 2]))
            ->assertOk()
            ->assertSee('Oldest pending listing');

        $this->actingAs($admin)
            ->get(route('admin.market.index', ['partial' => 1, 'status' => 'pending']))
            ->assertOk()
            ->assertDontSee('Oldest pending listing');
    }

    public function test_json_publish_updates_the_listing_without_a_redirect(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $listing = MarketListing::factory()->pending()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.market.publish', $listing))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'The listing has been published.',
            ]);

        $this->assertSame(MarketListingStatus::Published, $listing->fresh()->status);
        $this->assertSame(1, $owner->notifications()->count());
    }

    public function test_json_reject_keeps_the_community_guidelines_notice(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $listing = MarketListing::factory()->pending()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.market.reject', $listing))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(MarketListingStatus::Rejected, $listing->fresh()->status);
        $this->assertSame(MarketListingModerationResult::RejectedBody, $owner->notifications()->first()?->data['body']);
    }

    public function test_members_cannot_publish_listings_over_json(): void
    {
        $member = User::factory()->create();
        $listing = MarketListing::factory()->pending()->create();

        $this->actingAs($member)
            ->postJson(route('admin.market.publish', $listing))
            ->assertForbidden();

        $this->assertSame(MarketListingStatus::Pending, $listing->fresh()->status);
    }

    public function test_guests_are_redirected_to_login_when_reporting_a_listing(): void
    {
        $listing = MarketListing::factory()->create();

        $this->post(route('market.report', $listing), [
            'reason' => MarketListingReportReason::Spam->value,
        ])->assertRedirect(route('login'));
    }

    public function test_owners_cannot_report_their_own_listing(): void
    {
        $owner = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->post(route('market.report', $listing), [
                'reason' => MarketListingReportReason::Spam->value,
            ])
            ->assertForbidden();
    }

    public function test_a_visitor_can_report_a_published_listing_once(): void
    {
        $reporter = User::factory()->create();
        $listing = MarketListing::factory()->create();

        $this->actingAs($reporter)
            ->from(route('market.show', $listing))
            ->post(route('market.report', $listing), [
                'reason' => MarketListingReportReason::Scam->value,
                'details' => 'Looks like a fake giveaway.',
            ])
            ->assertRedirect(route('market.show', $listing))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('market_listing_reports', [
            'user_id' => $reporter->id,
            'market_listing_id' => $listing->id,
            'reason' => MarketListingReportReason::Scam->value,
            'status' => 'pending',
        ]);

        $this->actingAs($reporter)
            ->from(route('market.show', $listing))
            ->post(route('market.report', $listing), [
                'reason' => MarketListingReportReason::Spam->value,
            ])
            ->assertRedirect(route('market.show', $listing))
            ->assertSessionHas('error');

        $this->assertSame(1, $listing->reports()->count());
    }

    public function test_other_reports_require_details(): void
    {
        $reporter = User::factory()->create();
        $listing = MarketListing::factory()->create();

        $this->actingAs($reporter)
            ->from(route('market.show', $listing))
            ->post(route('market.report', $listing), [
                'reason' => MarketListingReportReason::Other->value,
            ])
            ->assertRedirect(route('market.show', $listing))
            ->assertSessionHasErrors('details');
    }

    public function test_publishing_marks_pending_reports_reviewed(): void
    {
        $admin = User::factory()->admin()->create();
        $listing = MarketListing::factory()->pending()->create();
        $report = MarketListingReport::factory()->create([
            'market_listing_id' => $listing->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.market.publish', $listing))
            ->assertRedirect();

        $this->assertSame('reviewed', $report->fresh()->status);
    }

    public function test_admin_can_mark_reports_reviewed_without_changing_status(): void
    {
        $admin = User::factory()->admin()->create();
        $listing = MarketListing::factory()->create();
        $report = MarketListingReport::factory()->create([
            'market_listing_id' => $listing->id,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.market.show', $listing))
            ->post(route('admin.market.reports.review', $listing))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(MarketListingStatus::Published, $listing->fresh()->status);
        $this->assertSame('reviewed', $report->fresh()->status);
    }

    public function test_admin_show_escapes_report_details(): void
    {
        $admin = User::factory()->admin()->create();
        $listing = MarketListing::factory()->create([
            'title' => 'Safe watering can',
        ]);
        MarketListingReport::factory()->create([
            'market_listing_id' => $listing->id,
            'details' => '<script>alert("xss")</script>',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.market.show', $listing))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert("xss")</script>', false);
    }
}
