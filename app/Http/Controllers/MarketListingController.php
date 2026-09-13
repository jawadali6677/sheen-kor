<?php

namespace App\Http\Controllers;

use App\Actions\FindOrCreateDirectConversation;
use App\Actions\ModerateContent;
use App\Enums\MarketListingCondition;
use App\Enums\MarketListingReportReason;
use App\Enums\MarketListingStatus;
use App\Enums\MarketListingType;
use App\Enums\ModerationDecision;
use App\Enums\Permission;
use App\Models\MarketCategory;
use App\Models\MarketListing;
use App\Models\MarketListingImage;
use App\Models\User;
use App\Notifications\MarketListingNeedsReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class MarketListingController extends Controller
{
    public function __construct(
        private ModerateContent $moderateContent,
    ) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->input('q', ''));
        $categoryId = $request->integer('category') ?: null;
        $type = $request->string('type')->toString();
        $condition = $request->string('condition')->toString();
        $location = trim((string) $request->input('location', ''));
        $nearLat = $request->filled('near_lat') ? $request->float('near_lat') : null;
        $nearLng = $request->filled('near_lng') ? $request->float('near_lng') : null;
        $radiusKm = $request->filled('radius_km') ? max(1, $request->float('radius_km')) : 25.0;

        $types = array_column(MarketListingType::cases(), 'value');
        $conditions = array_column(MarketListingCondition::cases(), 'value');

        if (! in_array($type, $types, true)) {
            $type = '';
        }

        if (! in_array($condition, $conditions, true)) {
            $condition = '';
        }

        if ($nearLat !== null && ($nearLat < -90 || $nearLat > 90)) {
            $nearLat = null;
            $nearLng = null;
        }

        if ($nearLng !== null && ($nearLng < -180 || $nearLng > 180)) {
            $nearLat = null;
            $nearLng = null;
        }

        $listings = MarketListing::query()
            ->published()
            ->with(['user', 'category'])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->when($categoryId, function ($query) use ($categoryId) {
                $query->where('market_category_id', $categoryId);
            })
            ->when($type !== '', function ($query) use ($type) {
                $query->where('listing_type', $type);
            })
            ->when($condition !== '', function ($query) use ($condition) {
                $query->where('condition', $condition);
            })
            ->when($location !== '', function ($query) use ($location) {
                $like = '%'.addcslashes($location, '%_\\').'%';
                $query->where('location_name', 'like', $like);
            })
            ->when($nearLat !== null && $nearLng !== null, function ($query) use ($nearLat, $nearLng, $radiusKm) {
                $this->constrainNearby($query, $nearLat, $nearLng, $radiusKm);
            })
            ->latest('published_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('market.index', [
            'listings' => $listings,
            'categories' => $this->activeCategories(),
            'types' => MarketListingType::cases(),
            'conditions' => MarketListingCondition::cases(),
            'search' => $search,
            'categoryId' => $categoryId,
            'type' => $type,
            'condition' => $condition,
            'location' => $location,
            'nearLat' => $nearLat,
            'nearLng' => $nearLng,
            'radiusKm' => $radiusKm,
        ]);
    }

    public function mine(Request $request)
    {
        $filter = $request->string('status')->toString();

        if (! in_array($filter, ['all', 'published', 'pending', 'rejected', 'closed'], true)) {
            $filter = 'all';
        }

        $listings = MarketListing::query()
            ->where('user_id', $request->user()->id)
            ->with('category')
            ->when($filter === 'published', fn ($query) => $query->where('status', MarketListingStatus::Published))
            ->when($filter === 'pending', fn ($query) => $query->where('status', MarketListingStatus::Pending))
            ->when($filter === 'rejected', fn ($query) => $query->where('status', MarketListingStatus::Rejected))
            ->when($filter === 'closed', function ($query) {
                $query->whereIn('status', [
                    MarketListingStatus::Sold,
                    MarketListingStatus::Exchanged,
                    MarketListingStatus::Donated,
                    MarketListingStatus::Closed,
                ]);
            })
            ->latest('updated_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('market.mine', [
            'listings' => $listings,
            'filter' => $filter,
        ]);
    }

    public function create()
    {
        $this->authorize('create', MarketListing::class);

        return view('market.create', [
            'categories' => $this->activeCategories(),
            'types' => MarketListingType::cases(),
            'conditions' => MarketListingCondition::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', MarketListing::class);

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

            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Something went wrong while creating your listing.');
        }

        $this->applyContentModeration($listing);

        return redirect()
            ->route('market.show', $listing)
            ->with('success', $this->moderationFlashMessage($listing, created: true));
    }

    public function show(MarketListing $listing)
    {
        if (
            ! $listing->status->isPubliclyVisible()
            && $listing->user_id !== auth()->id()
            && ! auth()->user()?->hasPermission(Permission::ModerateMarketListings)
        ) {
            abort(404);
        }

        $listing->load(['user', 'category', 'images']);

        $viewerHasReported = auth()->check()
            && $listing->reports()->where('user_id', auth()->id())->exists();

        return view('market.show', compact('listing', 'viewerHasReported'));
    }

    public function contact(Request $request, MarketListing $listing, FindOrCreateDirectConversation $findOrCreate): RedirectResponse
    {
        $this->authorize('contact', $listing);

        $listing->loadMissing('user');

        $conversation = $findOrCreate->handle($request->user(), $listing->user);

        return redirect()->route('messages.show', $conversation);
    }

    public function markSold(MarketListing $listing): RedirectResponse
    {
        return $this->completeListing($listing, 'markSold', MarketListingStatus::Sold, 'Listing marked as sold.');
    }

    public function markExchanged(MarketListing $listing): RedirectResponse
    {
        return $this->completeListing($listing, 'markExchanged', MarketListingStatus::Exchanged, 'Listing marked as exchanged.');
    }

    public function markDonated(MarketListing $listing): RedirectResponse
    {
        return $this->completeListing($listing, 'markDonated', MarketListingStatus::Donated, 'Listing marked as donated.');
    }

    public function close(MarketListing $listing): RedirectResponse
    {
        return $this->completeListing($listing, 'close', MarketListingStatus::Closed, 'Listing closed.');
    }

    public function report(Request $request, MarketListing $listing): RedirectResponse
    {
        $this->authorize('report', $listing);

        if ($listing->reports()->where('user_id', $request->user()->id)->exists()) {
            return back()->with('error', 'You have already reported this listing.');
        }

        $validated = $request->validate([
            'reason' => ['required', Rule::enum(MarketListingReportReason::class)],
            'details' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf($request->input('reason') === MarketListingReportReason::Other->value),
            ],
        ]);

        $listing->reports()->create([
            'user_id' => $request->user()->id,
            'reason' => MarketListingReportReason::from($validated['reason']),
            'details' => $validated['details'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Thanks. We will review this listing.');
    }

    public function edit(MarketListing $listing)
    {
        $this->authorize('update', $listing);

        $listing->load('images');

        return view('market.edit', [
            'listing' => $listing,
            'categories' => $this->activeCategories(),
            'types' => MarketListingType::cases(),
            'conditions' => MarketListingCondition::cases(),
        ]);
    }

    public function update(Request $request, MarketListing $listing)
    {
        $this->authorize('update', $listing);

        $validated = $this->validatedListing($request, creating: false);

        DB::beginTransaction();

        try {
            $listingType = MarketListingType::from($validated['listing_type']);

            $listing->update([
                'market_category_id' => $validated['market_category_id'],
                'title' => $validated['title'],
                'slug' => generateUniqueSlug(MarketListing::class, $validated['title'], $listing->id),
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
                'status' => MarketListingStatus::Pending,
                'published_at' => null,
            ]);

            if ($request->hasFile('featured_image')) {
                $oldFeaturedImage = $listing->featured_image;

                $listing->update([
                    'featured_image' => $request->file('featured_image')->store('market/featured', 'public'),
                ]);

                if ($oldFeaturedImage) {
                    Storage::disk('public')->delete($oldFeaturedImage);
                }
            }

            $this->storeGalleryImages($listing, $request);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            report($exception);

            return back()
                ->withInput()
                ->with('error', 'Something went wrong while updating your listing.');
        }

        $this->applyContentModeration($listing);

        return redirect()
            ->route('market.show', $listing)
            ->with('success', $this->moderationFlashMessage($listing, created: false));
    }

    public function destroy(MarketListing $listing)
    {
        $this->authorize('delete', $listing);

        DB::beginTransaction();

        try {
            if ($listing->featured_image) {
                Storage::disk('public')->delete($listing->featured_image);
            }

            foreach ($listing->images as $image) {
                Storage::disk('public')->delete($image->image);
                $image->delete();
            }

            $listing->delete();

            DB::commit();

            return redirect()
                ->route('market.index')
                ->with('success', 'Your listing has been deleted successfully.');
        } catch (Throwable $exception) {
            DB::rollBack();

            report($exception);

            return back()->with('error', 'Something went wrong while deleting your listing.');
        }
    }

    private function completeListing(
        MarketListing $listing,
        string $ability,
        MarketListingStatus $status,
        string $message,
    ): RedirectResponse {
        $this->authorize($ability, $listing);

        $listing->update([
            'status' => $status,
            'closed_at' => now(),
        ]);

        return back()->with('success', $message);
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

    private function applyContentModeration(MarketListing $listing): void
    {
        try {
            $listing->refresh()->load('images');

            $text = trim(implode("\n\n", array_filter([
                $listing->title,
                $listing->description,
                $listing->exchange_details,
            ], fn (?string $value): bool => filled($value))));

            $imagePaths = [];

            if (filled($listing->featured_image)) {
                $imagePaths[] = Storage::disk('public')->path($listing->featured_image);
            }

            foreach ($listing->images as $media) {
                $imagePaths[] = Storage::disk('public')->path($media->image);
            }

            $decision = $this->moderateContent->handle($text, $imagePaths, []);

            if ($decision === ModerationDecision::Allow) {
                $listing->update([
                    'status' => MarketListingStatus::Published,
                    'published_at' => now(),
                ]);

                return;
            }

            if ($decision === ModerationDecision::Reject) {
                $listing->update([
                    'status' => MarketListingStatus::Rejected,
                    'published_at' => null,
                ]);

                return;
            }

            $listing->update([
                'status' => MarketListingStatus::Pending,
                'published_at' => null,
            ]);

            $this->notifyReviewersIfPending($listing);
        } catch (Throwable $exception) {
            report($exception);

            $listing->update([
                'status' => MarketListingStatus::Pending,
                'published_at' => null,
            ]);

            $this->notifyReviewersIfPending($listing);
        }
    }

    private function notifyReviewersIfPending(MarketListing $listing): void
    {
        $listing->refresh()->loadMissing('user');

        if ($listing->status !== MarketListingStatus::Pending) {
            return;
        }

        $reviewers = User::query()->withPermission(Permission::ModerateMarketListings)->get();

        foreach ($reviewers as $reviewer) {
            $alreadyNotified = $reviewer->unreadNotifications()
                ->where('type', MarketListingNeedsReview::class)
                ->where('data->listing_id', $listing->id)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            try {
                $reviewer->notifyInbox(new MarketListingNeedsReview($listing));
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function moderationFlashMessage(MarketListing $listing, bool $created): string
    {
        $listing->refresh();

        return match ($listing->status) {
            MarketListingStatus::Published => $created
                ? 'Your listing has been published.'
                : 'Your listing has been updated and published.',
            MarketListingStatus::Rejected => 'Your listing was not published because it did not meet community guidelines.',
            default => $created
                ? 'Your listing has been submitted successfully and is awaiting review.'
                : 'Your listing has been updated successfully and is awaiting review.',
        };
    }

    /**
     * @param  Builder<MarketListing>  $query
     */
    private function constrainNearby(Builder $query, float $latitude, float $longitude, float $radiusKm): void
    {
        $latDelta = $radiusKm / 111.32;
        $cosLatitude = cos(deg2rad($latitude));
        $lngDelta = $cosLatitude == 0.0 ? 180 : $radiusKm / (111.32 * abs($cosLatitude));

        $query->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$latitude - $latDelta, $latitude + $latDelta])
            ->whereBetween('longitude', [$longitude - $lngDelta, $longitude + $lngDelta]);

        if ($query->getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        $query->whereRaw(
            '(6371 * acos(least(1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))) <= ?',
            [$latitude, $longitude, $latitude, $radiusKm],
        );
    }

    /**
     * @return Collection<int, MarketCategory>
     */
    private function activeCategories()
    {
        return MarketCategory::query()
            ->active()
            ->orderBy('name')
            ->get();
    }
}
