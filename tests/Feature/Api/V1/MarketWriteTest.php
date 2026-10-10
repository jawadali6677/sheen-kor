<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ListingPromotionStatus;
use App\Enums\MarketListingStatus;
use App\Enums\MarketListingType;
use App\Models\ListingPromotion;
use App\Models\MarketCategory;
use App\Models\MarketListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketWriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_create_update_and_delete_a_listing(): void
    {
        Storage::fake('public');
        $this->fakeSightengine();
        $user = User::factory()->create();
        $category = MarketCategory::factory()->create();

        $created = $this->post(route('api.v1.market.store'), $this->listingFields($category), $this->bearer($user));

        $created->assertCreated()
            ->assertJsonPath('status', 'published')
            ->assertJsonPath('message', 'Your listing has been published.')
            ->assertJsonPath('data.title', 'Wooden planter box for herbs');

        $listing = MarketListing::query()->firstOrFail();
        Storage::disk('public')->assertExists($listing->featured_image);

        $this->patch(route('api.v1.market.update', $listing), $this->listingFields($category, [
            'title' => 'Updated wooden planter for a community garden',
        ]), $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated wooden planter for a community garden')
            ->assertJsonPath('status', 'published')
            ->assertJsonPath('message', 'Your listing has been updated and published.');

        $this->deleteJson(route('api.v1.market.destroy', $listing), [], $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('message', 'Your listing has been deleted successfully.');

        $this->assertModelMissing($listing);
    }

    public function test_updating_a_listing_runs_moderation_again(): void
    {
        Storage::fake('public');
        $this->fakeSightengine(sexual: 0.95);
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $user->id,
            'status' => MarketListingStatus::Published,
        ]);

        $this->patch(route('api.v1.market.update', $listing), $this->listingFields($listing->category, [
            'title' => 'Unsafe listing title for review',
            'description' => 'This description is long enough to pass the minimum length rule.',
        ]), $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('message', 'Your listing was not published because it did not meet community guidelines.');

        $this->assertSame(MarketListingStatus::Rejected, $listing->refresh()->status);
        $this->assertNull($listing->published_at);
    }

    public function test_mine_filters_by_status_and_completion_cancels_an_open_promotion(): void
    {
        $user = User::factory()->create();
        $published = MarketListing::factory()->create([
            'user_id' => $user->id,
            'title' => 'Published chair',
            'status' => MarketListingStatus::Published,
        ]);
        MarketListing::factory()->pending()->create([
            'user_id' => $user->id,
            'title' => 'Pending chair',
        ]);
        $promotion = ListingPromotion::factory()->active()->create([
            'user_id' => $user->id,
            'market_listing_id' => $published->id,
        ]);

        $this->getJson(route('api.v1.market.mine', ['status' => 'published']), $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('filter', 'published')
            ->assertJsonPath('meta.per_page', 12)
            ->assertJsonPath('data.0.title', 'Published chair')
            ->assertJsonMissing(['title' => 'Pending chair']);

        $this->postJson(route('api.v1.market.sold', $published), [], $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('message', 'Listing marked as sold.')
            ->assertJsonPath('data.status', 'sold');

        $this->assertSame(ListingPromotionStatus::Cancelled, $promotion->refresh()->status);
    }

    public function test_completion_follows_the_listing_type_and_a_report_can_be_filed_once(): void
    {
        $seller = User::factory()->create();
        $buyer = User::factory()->create();
        $giveAway = MarketListing::factory()->giveAway()->create([
            'user_id' => $seller->id,
            'status' => MarketListingStatus::Published,
        ]);
        $forSale = MarketListing::factory()->create([
            'user_id' => $seller->id,
            'listing_type' => MarketListingType::Sell,
            'status' => MarketListingStatus::Published,
        ]);

        $this->postJson(route('api.v1.market.sold', $giveAway), [], $this->bearer($seller))
            ->assertForbidden();

        $this->postJson(route('api.v1.market.donated', $giveAway), [], $this->bearer($seller))
            ->assertOk()
            ->assertJsonPath('data.status', 'donated');

        $this->postJson(route('api.v1.market.report', $forSale), [
            'reason' => 'spam',
        ], $this->bearer($buyer))
            ->assertOk()
            ->assertJsonPath('message', 'Thanks. We will review this listing.');

        $this->postJson(route('api.v1.market.report', $forSale), [
            'reason' => 'scam',
        ], $this->bearer($buyer))
            ->assertUnprocessable()
            ->assertJsonPath('errors.listing.0', 'You have already reported this listing.');

        $this->assertSame(1, $forSale->reports()->count());
    }

    public function test_another_member_cannot_change_a_listing_and_a_disabled_account_cannot_create_one(): void
    {
        Storage::fake('public');
        $this->fakeSightengine();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $owner->id,
            'status' => MarketListingStatus::Published,
        ]);

        $this->patch(route('api.v1.market.update', $listing), $this->listingFields($listing->category), $this->bearer($other))
            ->assertForbidden();

        $this->deleteJson(route('api.v1.market.destroy', $listing), [], $this->bearer($other))
            ->assertForbidden();

        $disabled = User::factory()->create();
        $token = $disabled->createToken('Pixel 8', ['mobile'])->plainTextToken;
        $disabled->forceFill(['status' => false])->save();

        $this->post(route('api.v1.market.store'), $this->listingFields(MarketCategory::factory()->create()), $this->bearerToken($token))
            ->assertForbidden()
            ->assertJsonPath('message', 'This account has been disabled.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function listingFields(MarketCategory $category, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Wooden planter box for herbs',
            'description' => 'This sturdy planter still holds soil well and can be reused on a balcony.',
            'market_category_id' => $category->id,
            'listing_type' => 'sell',
            'condition' => 'good',
            'price' => '15.00',
            'location_name' => 'Erbil citadel garden',
            'featured_image' => UploadedFile::fake()->image('planter.jpg'),
        ], $overrides);
    }

    private function fakeSightengine(float $sexual = 0.01): void
    {
        config([
            'services.sightengine.user' => 'test-user',
            'services.sightengine.secret' => 'test-secret',
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'api.sightengine.com/*' => Http::response([
                'status' => 'success',
                'moderation_classes' => [
                    'sexual' => $sexual,
                    'discriminatory' => 0.01,
                    'insulting' => 0.01,
                    'violent' => 0.01,
                    'toxic' => 0.02,
                    'spam' => 0.01,
                ],
                'nudity' => ['sexual_activity' => 0.01, 'sexual_display' => 0.01, 'erotica' => 0.01, 'very_suggestive' => 0.05, 'none' => 0.9],
                'offensive' => ['prob' => 0.01],
                'gore' => ['prob' => 0.01],
                'violence' => ['prob' => 0.01],
                'faces' => [],
            ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function bearer(User $user): array
    {
        return $this->bearerToken($user->createToken('Pixel 8', ['mobile'])->plainTextToken);
    }

    /**
     * @return array<string, string>
     */
    private function bearerToken(string $token): array
    {
        Auth::forgetGuards();

        return [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];
    }
}
