<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CompleteMarketListing;
use App\Actions\CreateMarketListing;
use App\Actions\DeleteMarketListing;
use App\Actions\ReportMarketListing;
use App\Actions\UpdateMarketListing;
use App\Enums\MarketListingCondition;
use App\Enums\MarketListingStatus;
use App\Enums\MarketListingType;
use App\Exceptions\ContentWriteFailed;
use App\Http\Controllers\Api\V1\Concerns\SerializesApiContent;
use App\Http\Controllers\Controller;
use App\Models\MarketCategory;
use App\Models\MarketListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MarketListingController extends Controller
{
    use SerializesApiContent;

    public function categories(): JsonResponse
    {
        $categories = MarketCategory::query()
            ->active()
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $categories->map(fn (MarketCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
            ])->values(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $this->catalogFilters($request);

        $listings = MarketListing::query()
            ->published()
            ->with([
                'user' => function ($query): void {
                    $query->withExists([
                        'greenTickVerifications as has_active_green_tick' => function ($query): void {
                            $query->currentlyActive();
                        },
                    ]);
                },
                'category',
                'images',
            ])
            ->withCatalogPromotion($filters['category_id'])
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $like = '%'.addcslashes($filters['search'], '%_\\').'%';

                $query->where(function ($query) use ($like): void {
                    $query->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like);
                });
            })
            ->when($filters['category_id'], function ($query) use ($filters): void {
                $query->where('market_category_id', $filters['category_id']);
            })
            ->when($filters['type'] !== '', function ($query) use ($filters): void {
                $query->where('listing_type', $filters['type']);
            })
            ->when($filters['condition'] !== '', function ($query) use ($filters): void {
                $query->where('condition', $filters['condition']);
            })
            ->when($filters['location'] !== '', function ($query) use ($filters): void {
                $like = '%'.addcslashes($filters['location'], '%_\\').'%';
                $query->where('location_name', 'like', $like);
            })
            ->when($filters['near_lat'] !== null && $filters['near_lng'] !== null, function ($query) use ($filters): void {
                $query->nearby($filters['near_lat'], $filters['near_lng'], $filters['radius_km']);
            })
            ->orderByDesc('is_promoted_here')
            ->latest('published_at')
            ->latest('id')
            ->paginate(12);

        return response()->json([
            'data' => $listings->getCollection()->map(fn (MarketListing $listing): array => $this->listingPayload($listing))->values(),
            'meta' => $this->paginationMeta($listings),
        ]);
    }

    public function show(Request $request, MarketListing $listing): JsonResponse
    {
        abort_unless($listing->isVisibleTo($request->user()), 404);

        $listing->load([
            'user' => function ($query): void {
                $query->withExists([
                    'greenTickVerifications as has_active_green_tick' => function ($query): void {
                        $query->currentlyActive();
                    },
                ]);
            },
            'category',
            'images',
            'promotions' => function ($query): void {
                $query->currentlyActive();
            },
        ]);

        $viewer = $request->user();
        $reportedByUser = $viewer !== null
            && $listing->reports()->where('user_id', $viewer->id)->exists();

        return response()->json([
            'data' => $this->listingPayload($listing, $reportedByUser),
        ]);
    }

    /**
     * @return array{
     *     search: string,
     *     category_id: int|null,
     *     type: string,
     *     condition: string,
     *     location: string,
     *     near_lat: float|null,
     *     near_lng: float|null,
     *     radius_km: float
     * }
     */
    private function catalogFilters(Request $request): array
    {
        $search = trim($request->string('q')->toString());
        $categoryId = $request->integer('category') ?: null;
        $type = $request->string('type')->toString();
        $condition = $request->string('condition')->toString();
        $location = trim($request->string('location')->toString());
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

        return [
            'search' => $search,
            'category_id' => $categoryId,
            'type' => $type,
            'condition' => $condition,
            'location' => $location,
            'near_lat' => $nearLat,
            'near_lng' => $nearLng,
            'radius_km' => $radiusKm,
        ];
    }

    public function mine(Request $request): JsonResponse
    {
        $filter = $request->string('status')->toString();

        if (! in_array($filter, ['all', 'published', 'pending', 'rejected', 'closed'], true)) {
            $filter = 'all';
        }

        $listings = MarketListing::query()
            ->where('user_id', $request->user()->id)
            ->with(['user', 'category', 'images'])
            ->when($filter === 'published', fn ($query) => $query->where('status', MarketListingStatus::Published))
            ->when($filter === 'pending', fn ($query) => $query->where('status', MarketListingStatus::Pending))
            ->when($filter === 'rejected', fn ($query) => $query->where('status', MarketListingStatus::Rejected))
            ->when($filter === 'closed', function ($query): void {
                $query->whereIn('status', [
                    MarketListingStatus::Sold,
                    MarketListingStatus::Exchanged,
                    MarketListingStatus::Donated,
                    MarketListingStatus::Closed,
                ]);
            })
            ->latest('updated_at')
            ->latest('id')
            ->paginate(12);

        return response()->json([
            'filter' => $filter,
            'data' => $listings->getCollection()->map(fn (MarketListing $listing): array => $this->listingPayload($listing))->values(),
            'meta' => $this->paginationMeta($listings),
        ]);
    }

    public function store(Request $request, CreateMarketListing $createMarketListing): JsonResponse
    {
        $this->authorize('create', MarketListing::class);

        try {
            $listing = $createMarketListing->handle($request);
        } catch (ContentWriteFailed $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }

        $listing->refresh();

        return response()->json([
            'message' => $listing->moderationMessage(created: true),
            'status' => $listing->status->value,
            'data' => $this->listingPayload($this->loadWrittenListing($listing)),
        ], 201);
    }

    public function update(Request $request, MarketListing $listing, UpdateMarketListing $updateMarketListing): JsonResponse
    {
        $this->authorize('update', $listing);

        try {
            $listing = $updateMarketListing->handle($request, $listing);
        } catch (ContentWriteFailed $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }

        $listing->refresh();

        return response()->json([
            'message' => $listing->moderationMessage(created: false),
            'status' => $listing->status->value,
            'data' => $this->listingPayload($this->loadWrittenListing($listing)),
        ]);
    }

    public function destroy(MarketListing $listing, DeleteMarketListing $deleteMarketListing): JsonResponse
    {
        $this->authorize('delete', $listing);

        try {
            $deleteMarketListing->handle($listing);
        } catch (ContentWriteFailed $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }

        return response()->json([
            'message' => 'Your listing has been deleted successfully.',
        ]);
    }

    public function sold(MarketListing $listing, CompleteMarketListing $completeMarketListing): JsonResponse
    {
        return $this->complete($listing, 'markSold', MarketListingStatus::Sold, 'Listing marked as sold.', $completeMarketListing);
    }

    public function exchanged(MarketListing $listing, CompleteMarketListing $completeMarketListing): JsonResponse
    {
        return $this->complete($listing, 'markExchanged', MarketListingStatus::Exchanged, 'Listing marked as exchanged.', $completeMarketListing);
    }

    public function donated(MarketListing $listing, CompleteMarketListing $completeMarketListing): JsonResponse
    {
        return $this->complete($listing, 'markDonated', MarketListingStatus::Donated, 'Listing marked as donated.', $completeMarketListing);
    }

    public function close(MarketListing $listing, CompleteMarketListing $completeMarketListing): JsonResponse
    {
        return $this->complete($listing, 'close', MarketListingStatus::Closed, 'Listing closed.', $completeMarketListing);
    }

    public function report(Request $request, MarketListing $listing, ReportMarketListing $reportMarketListing): JsonResponse
    {
        $this->authorize('report', $listing);

        if (! $reportMarketListing->handle($request, $listing)) {
            throw ValidationException::withMessages([
                'listing' => 'You have already reported this listing.',
            ]);
        }

        return response()->json([
            'message' => 'Thanks. We will review this listing.',
        ]);
    }

    private function complete(
        MarketListing $listing,
        string $ability,
        MarketListingStatus $status,
        string $message,
        CompleteMarketListing $completeMarketListing,
    ): JsonResponse {
        $this->authorize($ability, $listing);

        $completeMarketListing->handle($listing, $status);

        return response()->json([
            'message' => $message,
            'data' => $this->listingPayload($this->loadWrittenListing($listing)),
        ]);
    }

    private function loadWrittenListing(MarketListing $listing): MarketListing
    {
        return $listing->refresh()->load(['user', 'category', 'images']);
    }
}
