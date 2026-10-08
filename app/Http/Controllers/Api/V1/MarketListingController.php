<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MarketListingCondition;
use App\Enums\MarketListingType;
use App\Http\Controllers\Api\V1\Concerns\SerializesApiContent;
use App\Http\Controllers\Controller;
use App\Models\MarketCategory;
use App\Models\MarketListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
