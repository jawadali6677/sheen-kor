<?php

namespace App\Http\Controllers;

use App\Actions\CompleteMarketListing;
use App\Actions\CreateMarketListing;
use App\Actions\DeleteMarketListing;
use App\Actions\FindOrCreateDirectConversation;
use App\Actions\ReportMarketListing;
use App\Actions\UpdateMarketListing;
use App\Enums\MarketListingCondition;
use App\Enums\MarketListingStatus;
use App\Enums\MarketListingType;
use App\Exceptions\ContentWriteFailed;
use App\Models\MarketCategory;
use App\Models\MarketListing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MarketListingController extends Controller
{
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
            ->withCatalogPromotion($categoryId)
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
                $query->nearby($nearLat, $nearLng, $radiusKm);
            })
            ->orderByDesc('is_promoted_here')
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
            ->with(['category', 'promotions.order'])
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

    public function store(Request $request, CreateMarketListing $createMarketListing)
    {
        $this->authorize('create', MarketListing::class);

        try {
            $listing = $createMarketListing->handle($request);
        } catch (ContentWriteFailed $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('market.show', $listing)
            ->with('success', $listing->moderationMessage(created: true));
    }

    public function show(MarketListing $listing)
    {
        abort_unless($listing->isVisibleTo(auth()->user()), 404);

        $listing->load(['user', 'category', 'images', 'promotions.order']);

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

    public function markSold(MarketListing $listing, CompleteMarketListing $completeMarketListing): RedirectResponse
    {
        return $this->completeListing($listing, 'markSold', MarketListingStatus::Sold, 'Listing marked as sold.', $completeMarketListing);
    }

    public function markExchanged(MarketListing $listing, CompleteMarketListing $completeMarketListing): RedirectResponse
    {
        return $this->completeListing($listing, 'markExchanged', MarketListingStatus::Exchanged, 'Listing marked as exchanged.', $completeMarketListing);
    }

    public function markDonated(MarketListing $listing, CompleteMarketListing $completeMarketListing): RedirectResponse
    {
        return $this->completeListing($listing, 'markDonated', MarketListingStatus::Donated, 'Listing marked as donated.', $completeMarketListing);
    }

    public function close(MarketListing $listing, CompleteMarketListing $completeMarketListing): RedirectResponse
    {
        return $this->completeListing($listing, 'close', MarketListingStatus::Closed, 'Listing closed.', $completeMarketListing);
    }

    public function report(Request $request, MarketListing $listing, ReportMarketListing $reportMarketListing): RedirectResponse
    {
        $this->authorize('report', $listing);

        if (! $reportMarketListing->handle($request, $listing)) {
            return back()->with('error', 'You have already reported this listing.');
        }

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

    public function update(Request $request, MarketListing $listing, UpdateMarketListing $updateMarketListing)
    {
        $this->authorize('update', $listing);

        try {
            $listing = $updateMarketListing->handle($request, $listing);
        } catch (ContentWriteFailed $exception) {
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('market.show', $listing)
            ->with('success', $listing->moderationMessage(created: false));
    }

    public function destroy(MarketListing $listing, DeleteMarketListing $deleteMarketListing)
    {
        $this->authorize('delete', $listing);

        try {
            $deleteMarketListing->handle($listing);
        } catch (ContentWriteFailed $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('market.index')
            ->with('success', 'Your listing has been deleted successfully.');
    }

    private function completeListing(
        MarketListing $listing,
        string $ability,
        MarketListingStatus $status,
        string $message,
        CompleteMarketListing $completeMarketListing,
    ): RedirectResponse {
        $this->authorize($ability, $listing);

        $completeMarketListing->handle($listing, $status);

        return back()->with('success', $message);
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
