<?php

namespace Tests\Feature;

use App\Enums\MarketListingStatus;
use App\Enums\MarketListingType;
use App\Models\Conversation;
use App\Models\MarketListing;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketListingOwnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_my_market(): void
    {
        $this->get(route('market.mine'))
            ->assertRedirect(route('login'));
    }

    public function test_my_market_only_shows_the_owners_listings(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        MarketListing::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Owner planter box',
        ]);
        MarketListing::factory()->create([
            'user_id' => $other->id,
            'title' => 'Other bicycle light',
        ]);

        $this->actingAs($owner)
            ->get(route('market.mine'))
            ->assertOk()
            ->assertSee('Owner planter box')
            ->assertDontSee('Other bicycle light');
    }

    public function test_my_market_closed_filter_includes_completed_listings(): void
    {
        $owner = User::factory()->create();
        MarketListing::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Live chair',
        ]);
        MarketListing::factory()->sold()->create([
            'user_id' => $owner->id,
            'title' => 'Sold lamp',
        ]);

        $this->actingAs($owner)
            ->get(route('market.mine', ['status' => 'closed']))
            ->assertOk()
            ->assertSee('Sold lamp')
            ->assertDontSee('Live chair');
    }

    public function test_owner_can_mark_a_sell_listing_sold(): void
    {
        $owner = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $owner->id,
            'listing_type' => MarketListingType::Sell,
        ]);

        $this->actingAs($owner)
            ->from(route('market.mine'))
            ->post(route('market.sold', $listing))
            ->assertRedirect(route('market.mine'));

        $listing->refresh();

        $this->assertSame(MarketListingStatus::Sold, $listing->status);
        $this->assertNotNull($listing->closed_at);
    }

    public function test_owner_cannot_mark_a_give_away_listing_sold(): void
    {
        $owner = User::factory()->create();
        $listing = MarketListing::factory()->giveAway()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->post(route('market.sold', $listing))
            ->assertForbidden();

        $this->assertSame(MarketListingStatus::Published, $listing->fresh()->status);
    }

    public function test_other_users_cannot_close_someone_elses_listing(): void
    {
        $listing = MarketListing::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('market.close', $listing))
            ->assertForbidden();
    }

    public function test_contact_seller_opens_the_existing_direct_conversation_without_a_message(): void
    {
        $owner = User::factory()->create();
        $buyer = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($buyer)
            ->post(route('market.contact', $listing));

        $response->assertRedirect();
        $this->assertSame(1, Conversation::query()->count());
        $this->assertSame(0, Message::query()->count());
        $response->assertRedirect(route('messages.show', Conversation::query()->first()));
    }

    public function test_owner_cannot_contact_themselves(): void
    {
        $owner = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->post(route('market.contact', $listing))
            ->assertForbidden();
    }

    public function test_guests_are_redirected_when_contacting_a_seller(): void
    {
        $listing = MarketListing::factory()->create();

        $this->post(route('market.contact', $listing))
            ->assertRedirect(route('login'));
    }

    public function test_profile_market_tab_shows_published_listings_only(): void
    {
        $profile = User::factory()->create();

        MarketListing::factory()->create([
            'user_id' => $profile->id,
            'title' => 'Published watering can',
        ]);
        MarketListing::factory()->pending()->create([
            'user_id' => $profile->id,
            'title' => 'Pending watering can',
        ]);
        MarketListing::factory()->sold()->create([
            'user_id' => $profile->id,
            'title' => 'Sold watering can',
        ]);

        $this->actingAs($profile)
            ->get(route('users.show', ['user' => $profile, 'tab' => 'market']))
            ->assertOk()
            ->assertSee('Published watering can')
            ->assertDontSee('Pending watering can')
            ->assertDontSee('Sold watering can');
    }
}
