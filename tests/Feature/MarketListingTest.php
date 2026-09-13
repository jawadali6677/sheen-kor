<?php

namespace Tests\Feature;

use App\Enums\MarketListingStatus;
use App\Enums\MarketListingType;
use App\Enums\Permission;
use App\Models\MarketCategory;
use App\Models\MarketListing;
use App\Models\User;
use App\Notifications\MarketListingNeedsReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_create_a_listing(): void
    {
        $this->get(route('market.create'))
            ->assertRedirect(route('login'));

        $this->post(route('market.store'), [])
            ->assertRedirect(route('login'));
    }

    public function test_authorized_users_can_open_the_create_form(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('market.create'))
            ->assertOk();
    }

    public function test_listing_validation_requires_core_fields(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create())
            ->post(route('market.store'), [])
            ->assertSessionHasErrors([
                'title',
                'description',
                'market_category_id',
                'listing_type',
                'condition',
                'location_name',
                'featured_image',
            ]);
    }

    public function test_sell_listings_require_a_price(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create())
            ->post(route('market.store'), $this->listingPayload(
                listingType: MarketListingType::Sell,
                extras: ['price' => null],
            ))
            ->assertSessionHasErrors(['price']);
    }

    public function test_give_away_listings_cannot_include_a_price(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create())
            ->post(route('market.store'), $this->listingPayload(
                listingType: MarketListingType::GiveAway,
                extras: ['price' => '12.50'],
            ))
            ->assertSessionHasErrors(['price']);
    }

    public function test_exchange_listings_require_exchange_details(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->create())
            ->post(route('market.store'), $this->listingPayload(
                listingType: MarketListingType::Exchange,
                extras: ['exchange_details' => null],
            ))
            ->assertSessionHasErrors(['exchange_details']);
    }

    public function test_safe_listings_are_published_automatically(): void
    {
        Storage::fake('public');
        $this->withSightengineCredentials();
        $this->fakeSightengine();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('market.store'), $this->listingPayload())
            ->assertRedirect()
            ->assertSessionHas('success', 'Your listing has been published.');

        $listing = MarketListing::query()->firstOrFail();

        $this->assertSame($user->id, $listing->user_id);
        $this->assertSame(MarketListingStatus::Published, $listing->status);
        $this->assertNotNull($listing->published_at);
        $this->assertNotNull($listing->slug);
        Storage::disk('public')->assertExists($listing->featured_image);
        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), '/1.0/text/check.json')) {
                return false;
            }

            parse_str($request->body(), $body);

            return str_contains((string) ($body['text'] ?? ''), 'Wooden planter box')
                && str_contains((string) ($body['text'] ?? ''), 'still holds soil well');
        });
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/1.0/check.json'));
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/1.0/video/check-sync.json'));
    }

    public function test_gallery_images_are_stored_on_the_public_disk(): void
    {
        Storage::fake('public');
        $this->withSightengineCredentials();
        $this->fakeSightengine();

        $this->actingAs(User::factory()->create())
            ->post(route('market.store'), [
                ...$this->listingPayload(),
                'images' => [UploadedFile::fake()->image('extra.jpg')],
            ])
            ->assertRedirect();

        $listing = MarketListing::query()->firstOrFail();

        $this->assertCount(1, $listing->images);
        Storage::disk('public')->assertExists($listing->images->first()->image);
        $this->assertSame('image', $listing->images->first()->media_type);
    }

    public function test_videos_are_rejected_and_not_stored(): void
    {
        Storage::fake('public');
        $this->withSightengineCredentials();
        $this->fakeSightengine();

        $this->actingAs(User::factory()->create())
            ->post(route('market.store'), [
                ...$this->listingPayload(),
                'videos' => [UploadedFile::fake()->create('clip.mp4', 400, 'video/mp4')],
            ])
            ->assertSessionHasErrors(['videos']);

        $this->assertSame(0, MarketListing::query()->count());
    }

    public function test_unsafe_listings_are_rejected(): void
    {
        Storage::fake('public');
        $this->withSightengineCredentials();
        $this->fakeSightengine(text: $this->textPayload(['sexual' => 0.96]));

        $this->actingAs(User::factory()->create())
            ->post(route('market.store'), $this->listingPayload())
            ->assertRedirect()
            ->assertSessionHas('success', 'Your listing was not published because it did not meet community guidelines.');

        $listing = MarketListing::query()->firstOrFail();

        $this->assertSame(MarketListingStatus::Rejected, $listing->status);
        $this->assertNull($listing->published_at);
    }

    public function test_moderation_api_failure_keeps_the_listing_pending_and_notifies_reviewers(): void
    {
        Storage::fake('public');
        $this->withSightengineCredentials();
        Http::preventStrayRequests();
        Http::fake([
            'api.sightengine.com/*' => Http::response(['status' => 'failure'], 500),
        ]);

        $moderator = User::factory()->moderator()->create();
        $admin = User::factory()->admin()->create();
        $extra = User::factory()->create();
        $extra->syncExtraPermissions([Permission::ModerateMarketListings->value]);
        $member = User::factory()->create();
        $disabledAdmin = User::factory()->admin()->disabled()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('market.store'), $this->listingPayload())
            ->assertRedirect()
            ->assertSessionHas('success', 'Your listing has been submitted successfully and is awaiting review.');

        $listing = MarketListing::query()->firstOrFail();

        $this->assertSame(MarketListingStatus::Pending, $listing->status);
        $this->assertNull($listing->published_at);
        $this->assertSame(1, $moderator->notifications()->count());
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $extra->notifications()->count());
        $this->assertSame(0, $member->notifications()->count());
        $this->assertSame(0, $disabledAdmin->notifications()->count());
        $this->assertSame(MarketListingNeedsReview::class, $moderator->notifications()->first()?->type);
        $this->assertSame('market_listing_needs_review', $moderator->notifications()->first()?->data['kind']);
        $this->assertSame(route('admin.market.show', $listing), $moderator->notifications()->first()?->data['url']);
    }

    public function test_pending_review_notifications_are_not_duplicated_while_unread(): void
    {
        Storage::fake('public');
        $this->withSightengineCredentials();
        $this->fakeSightengine(text: $this->textPayload(['toxic' => 0.55]));

        $author = User::factory()->create();
        $moderator = User::factory()->moderator()->create();

        $this->actingAs($author)
            ->post(route('market.store'), $this->listingPayload())
            ->assertRedirect();

        $listing = MarketListing::query()->firstOrFail();

        $this->actingAs($author)
            ->put(route('market.update', $listing), $this->updatePayload($listing))
            ->assertRedirect();

        $this->assertSame(1, $moderator->fresh()->unreadNotifications()->count());
    }

    public function test_automatic_publish_and_reject_do_not_notify_reviewers(): void
    {
        Storage::fake('public');
        $this->withSightengineCredentials();
        $moderator = User::factory()->moderator()->create();

        $this->fakeSightengine();

        $this->actingAs(User::factory()->create())
            ->post(route('market.store'), $this->listingPayload())
            ->assertRedirect();

        $this->assertSame(0, $moderator->notifications()->count());

        $this->fakeSightengine(text: $this->textPayload(['sexual' => 0.96]));

        $this->actingAs(User::factory()->create())
            ->post(route('market.store'), $this->listingPayload())
            ->assertRedirect();

        $this->assertSame(0, $moderator->fresh()->notifications()->count());
    }

    public function test_editing_a_published_listing_is_remoderated(): void
    {
        Storage::fake('public');
        $this->withSightengineCredentials();
        $this->fakeSightengine();

        $user = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $user->id,
            'status' => MarketListingStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        Storage::disk('public')->put($listing->featured_image, 'cover');

        $this->actingAs($user)
            ->put(route('market.update', $listing), $this->updatePayload($listing, 'Updated wooden planter for a balcony garden'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Your listing has been updated and published.');

        $listing->refresh();

        $this->assertSame(MarketListingStatus::Published, $listing->status);
        $this->assertNotNull($listing->published_at);
        $this->assertSame('Updated wooden planter for a balcony garden', $listing->title);
    }

    public function test_editing_a_published_listing_stays_pending_when_moderation_fails(): void
    {
        Storage::fake('public');
        $this->withSightengineCredentials();
        Http::preventStrayRequests();
        Http::fake([
            'api.sightengine.com/*' => Http::response(['status' => 'failure'], 503),
        ]);

        $user = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $user->id,
            'status' => MarketListingStatus::Published,
            'published_at' => now()->subDay(),
        ]);

        Storage::disk('public')->put($listing->featured_image, 'cover');

        $this->actingAs($user)
            ->put(route('market.update', $listing), $this->updatePayload($listing))
            ->assertRedirect();

        $listing->refresh();

        $this->assertSame(MarketListingStatus::Pending, $listing->status);
        $this->assertNull($listing->published_at);
    }

    public function test_public_users_cannot_view_pending_listings(): void
    {
        $listing = MarketListing::factory()->pending()->create();

        $this->get(route('market.show', $listing))
            ->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->get(route('market.show', $listing))
            ->assertNotFound();
    }

    public function test_owners_and_moderators_can_view_pending_listings(): void
    {
        $owner = User::factory()->create();
        $listing = MarketListing::factory()->pending()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('market.show', $listing))
            ->assertOk();

        $this->actingAs(User::factory()->moderator()->create())
            ->get(route('market.show', $listing))
            ->assertOk();
    }

    public function test_unauthorized_users_cannot_edit_or_delete_another_users_listing(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($other)
            ->get(route('market.edit', $listing))
            ->assertForbidden();

        $this->actingAs($other)
            ->put(route('market.update', $listing), $this->updatePayload($listing))
            ->assertForbidden();

        $this->actingAs($other)
            ->delete(route('market.destroy', $listing))
            ->assertForbidden();

        $this->assertDatabaseHas('market_listings', [
            'id' => $listing->id,
        ]);
    }

    public function test_owners_can_delete_their_listings(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $listing = MarketListing::factory()->create([
            'user_id' => $user->id,
            'featured_image' => 'market/featured/cover.jpg',
        ]);

        Storage::disk('public')->put($listing->featured_image, 'cover');

        $this->actingAs($user)
            ->delete(route('market.destroy', $listing))
            ->assertRedirect(route('market.index'));

        $this->assertDatabaseMissing('market_listings', [
            'id' => $listing->id,
        ]);
        Storage::disk('public')->assertMissing('market/featured/cover.jpg');
    }

    public function test_completed_listings_cannot_be_edited(): void
    {
        $user = User::factory()->create();
        $listing = MarketListing::factory()->sold()->create([
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('market.edit', $listing))
            ->assertForbidden();
    }

    private function withSightengineCredentials(): void
    {
        config([
            'services.sightengine.user' => 'test-user',
            'services.sightengine.secret' => 'test-secret',
        ]);
    }

    /**
     * @param  array<string, float>  $classes
     * @param  array<string, mixed>  $image
     */
    private function fakeSightengine(?array $text = null, ?array $image = null): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.sightengine.com/1.0/text/check.json' => Http::response($text ?? $this->textPayload()),
            'api.sightengine.com/1.0/check.json' => Http::response($image ?? $this->communityPhotoPayload()),
            'api.sightengine.com/1.0/video/check-sync.json' => Http::response(['status' => 'success', 'data' => ['frames' => []]]),
        ]);
    }

    /**
     * @param  array<string, float>  $overrides
     * @return array<string, mixed>
     */
    private function textPayload(array $overrides = []): array
    {
        return [
            'status' => 'success',
            'moderation_classes' => array_merge([
                'sexual' => 0.01,
                'discriminatory' => 0.01,
                'insulting' => 0.01,
                'violent' => 0.01,
                'toxic' => 0.02,
                'spam' => 0.01,
            ], $overrides),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function communityPhotoPayload(): array
    {
        return [
            'status' => 'success',
            'nudity' => [
                'sexual_activity' => 0.01,
                'sexual_display' => 0.01,
                'erotica' => 0.02,
                'very_suggestive' => 0.05,
                'suggestive' => 0.18,
                'mildly_suggestive' => 0.22,
                'none' => 0.81,
                'suggestive_classes' => [
                    'cleavage' => 0.12,
                    'male_chest' => 0.08,
                ],
            ],
            'faces' => [
                ['prob' => 0.99],
            ],
            'offensive' => ['prob' => 0.02],
            'gore' => ['prob' => 0.01],
            'violence' => ['prob' => 0.02],
        ];
    }

    /**
     * @param  array<string, mixed>  $extras
     * @return array<string, mixed>
     */
    private function listingPayload(
        ?MarketCategory $category = null,
        MarketListingType $listingType = MarketListingType::Sell,
        array $extras = [],
    ): array {
        $category ??= MarketCategory::factory()->create();

        $payload = [
            'title' => 'Wooden planter box for herbs',
            'description' => 'This sturdy planter still holds soil well and can be reused on a balcony.',
            'market_category_id' => $category->id,
            'listing_type' => $listingType->value,
            'condition' => 'good',
            'location_name' => 'Erbil citadel garden',
            'latitude' => 36.1911,
            'longitude' => 44.0092,
            'featured_image' => UploadedFile::fake()->image('planter.jpg'),
            'price' => $listingType === MarketListingType::Sell ? '15.00' : null,
            'exchange_details' => $listingType === MarketListingType::Exchange
                ? 'Looking for a watering can or small pots.'
                : null,
        ];

        return array_merge($payload, $extras);
    }

    /**
     * @return array<string, mixed>
     */
    private function updatePayload(MarketListing $listing, string $title = 'Updated wooden planter for a community garden'): array
    {
        return [
            'title' => $title,
            'description' => $listing->description,
            'market_category_id' => $listing->market_category_id,
            'listing_type' => $listing->listing_type->value,
            'condition' => $listing->condition->value,
            'location_name' => $listing->location_name,
            'latitude' => $listing->latitude,
            'longitude' => $listing->longitude,
            'price' => $listing->listing_type === MarketListingType::Sell ? $listing->price : null,
            'exchange_details' => $listing->exchange_details,
        ];
    }
}
