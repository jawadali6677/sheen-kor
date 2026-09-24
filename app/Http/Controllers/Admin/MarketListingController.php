<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarketListingStatus;
use App\Http\Controllers\Controller;
use App\Models\MarketListing;
use App\Notifications\MarketListingModerationResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class MarketListingController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('moderate', MarketListing::class);

        $status = $request->string('status')->toString();

        if (! in_array($status, ['pending', 'published', 'rejected', 'all', 'reported'], true)) {
            $status = 'pending';
        }

        $search = trim((string) $request->input('q', ''));

        $listings = MarketListing::query()
            ->with([
                'user',
                'category',
                'latestPromotion',
                'promotions' => function ($query): void {
                    $query->currentlyActive();
                },
            ])
            ->withCount([
                'reports as pending_reports_count' => function ($query) {
                    $query->where('status', 'pending');
                },
            ])
            ->when($status === 'reported', function ($query) {
                $query->whereHas('reports', function ($query) {
                    $query->where('status', 'pending');
                });
            })
            ->when(! in_array($status, ['all', 'reported'], true), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas('user', function ($query) use ($like) {
                            $query->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        });
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $data = [
            'listings' => $listings,
            'status' => $status,
            'search' => $search,
            'counts' => $this->statusCounts(),
        ];

        if ($request->boolean('partial') || $request->headers->has('X-Infinite-Scroll')) {
            return view('admin.market.partials.results', $data);
        }

        return view('admin.market.index', $data);
    }

    public function show(MarketListing $listing): View
    {
        $this->authorize('moderate', MarketListing::class);

        $listing->load(['user', 'category', 'images', 'reports.user', 'promotions', 'latestPromotion']);

        return view('admin.market.show', compact('listing'));
    }

    public function publish(Request $request, MarketListing $listing): RedirectResponse|JsonResponse
    {
        return $this->changeStatus($request, $listing, MarketListingStatus::Published);
    }

    public function pending(Request $request, MarketListing $listing): RedirectResponse|JsonResponse
    {
        return $this->changeStatus($request, $listing, MarketListingStatus::Pending);
    }

    public function reject(Request $request, MarketListing $listing): RedirectResponse|JsonResponse
    {
        return $this->changeStatus($request, $listing, MarketListingStatus::Rejected);
    }

    public function reviewReports(Request $request, MarketListing $listing): RedirectResponse|JsonResponse
    {
        $this->authorize('moderate', MarketListing::class);

        $this->markPendingReportsReviewed($listing);

        return $this->moderationResponse($request, 'Reports have been marked as reviewed.');
    }

    public function destroy(Request $request, MarketListing $listing): RedirectResponse|JsonResponse
    {
        $this->authorize('moderate', MarketListing::class);

        $listing->loadMissing('images');

        DB::beginTransaction();

        try {
            if ($listing->featured_image) {
                Storage::disk('public')->delete($listing->featured_image);
            }

            foreach ($listing->images as $image) {
                Storage::disk('public')->delete($image->image);
                $image->delete();
            }

            $listing->reports()->delete();
            $listing->delete();

            DB::commit();

            return $this->moderationResponse($request, 'The listing has been deleted.');
        } catch (Throwable $exception) {
            DB::rollBack();

            report($exception);

            return $this->moderationResponse(
                $request,
                'Something went wrong while deleting this listing.',
                error: true,
            );
        }
    }

    private function changeStatus(
        Request $request,
        MarketListing $listing,
        MarketListingStatus $status,
    ): RedirectResponse|JsonResponse {
        $this->authorize('moderate', MarketListing::class);

        $wasPending = $listing->status === MarketListingStatus::Pending;

        $listing->update([
            'status' => $status,
            'published_at' => $status === MarketListingStatus::Published ? now() : null,
            'closed_at' => $status === MarketListingStatus::Published ? null : $listing->closed_at,
        ]);

        if (in_array($status, [MarketListingStatus::Published, MarketListingStatus::Rejected], true)) {
            $this->markPendingReportsReviewed($listing);
        }

        if ($wasPending && in_array($status, [MarketListingStatus::Published, MarketListingStatus::Rejected], true)) {
            $this->notifyOwnerOfModerationResult($listing, $status);
        }

        $message = match ($status) {
            MarketListingStatus::Published => 'The listing has been published.',
            MarketListingStatus::Rejected => 'The listing has been rejected.',
            default => 'The listing is pending review.',
        };

        return $this->moderationResponse($request, $message);
    }

    private function moderationResponse(Request $request, string $message, bool $error = false): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => ! $error,
                'message' => $message,
                'counts' => $this->statusCounts(),
            ], $error ? 500 : 200);
        }

        if ($error) {
            return back()->with('error', $message);
        }

        if ($request->routeIs('admin.market.destroy')) {
            return redirect()
                ->route('admin.market.index')
                ->with('success', $message);
        }

        return back()->with('success', $message);
    }

    /**
     * @return array{pending: int, published: int, rejected: int, reported: int, total: int}
     */
    private function statusCounts(): array
    {
        return [
            'pending' => MarketListing::query()->where('status', MarketListingStatus::Pending)->count(),
            'published' => MarketListing::query()->where('status', MarketListingStatus::Published)->count(),
            'rejected' => MarketListing::query()->where('status', MarketListingStatus::Rejected)->count(),
            'reported' => MarketListing::query()
                ->whereHas('reports', function ($query) {
                    $query->where('status', 'pending');
                })
                ->count(),
            'total' => MarketListing::query()->count(),
        ];
    }

    private function markPendingReportsReviewed(MarketListing $listing): void
    {
        $listing->reports()
            ->where('status', 'pending')
            ->update(['status' => 'reviewed']);
    }

    private function notifyOwnerOfModerationResult(MarketListing $listing, MarketListingStatus $status): void
    {
        $owner = $listing->user;

        if (! $owner || $owner->id === auth()->id()) {
            return;
        }

        $outcome = $status === MarketListingStatus::Published
            ? MarketListingModerationResult::OutcomePublished
            : MarketListingModerationResult::OutcomeRejected;

        try {
            $owner->notifyInbox(new MarketListingModerationResult($listing, $outcome));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
