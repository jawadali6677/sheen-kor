<?php

namespace App\Actions;

use App\Enums\MarketListingCondition;
use App\Enums\MarketListingStatus;
use App\Enums\MarketListingType;
use App\Exceptions\ContentWriteFailed;
use App\Models\MarketListing;
use App\Models\MarketListingImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateMarketListing
{
    public function __construct(private ModerateMarketListing $moderateMarketListing) {}

    public function handle(Request $request): MarketListing
    {
        $validated = $this->validatedListing($request, creating: true);

        DB::beginTransaction();

        try {
            $listingType = MarketListingType::from($validated['listing_type']);

            $listing = MarketListing::query()->create([
                'user_id' => $request->user()->id,
                'market_category_id' => $validated['market_category_id'],
                'title' => $validated['title'],
                'slug' => generateUniqueSlug(MarketListing::class, $validated['title']),
                'description' => $validated['description'],
                'listing_type' => $listingType,
                'condition' => MarketListingCondition::from($validated['condition']),
                'price' => $listingType->priceIsRequired() ? $validated['price'] : null,
                'exchange_details' => $listingType->exchangeDetailsAreRequired()
                    ? $validated['exchange_details']
                    : null,
                'location_name' => $validated['location_name'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'featured_image' => $request->file('featured_image')->store('market/featured', 'public'),
                'status' => MarketListingStatus::Pending,
                'published_at' => null,
            ]);

            $this->storeGalleryImages($listing, $request);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            report($exception);

            throw new ContentWriteFailed('Something went wrong while creating your listing.', previous: $exception);
        }

        $this->moderateMarketListing->handle($listing);

        return $listing;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedListing(Request $request, bool $creating): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:20'],
            'market_category_id' => [
                'required',
                'integer',
                Rule::exists('market_categories', 'id')->where('is_active', true),
            ],
            'listing_type' => ['required', Rule::enum(MarketListingType::class)],
            'condition' => ['required', Rule::enum(MarketListingCondition::class)],
            'price' => [
                'required_if:listing_type,'.MarketListingType::Sell->value,
                'prohibited_unless:listing_type,'.MarketListingType::Sell->value,
                'nullable',
                'numeric',
                'min:0.01',
            ],
            'exchange_details' => [
                'required_if:listing_type,'.MarketListingType::Exchange->value,
                'prohibited_unless:listing_type,'.MarketListingType::Exchange->value,
                'nullable',
                'string',
                'min:5',
            ],
            'location_name' => ['required', 'string', 'min:3', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'featured_image' => array_filter([
                $creating ? 'required' : 'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ]),
            'images' => ['nullable', 'array', 'max:7'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'videos' => ['prohibited'],
            'videos.*' => ['prohibited'],
        ]);
    }

    private function storeGalleryImages(MarketListing $listing, Request $request): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $sortOrder = $listing->images()->count();

        foreach ($request->file('images') as $image) {
            MarketListingImage::query()->create([
                'market_listing_id' => $listing->id,
                'image' => $image->store('market/images', 'public'),
                'caption' => null,
                'sort_order' => $sortOrder,
                'media_type' => 'image',
            ]);

            $sortOrder++;
        }
    }
}
