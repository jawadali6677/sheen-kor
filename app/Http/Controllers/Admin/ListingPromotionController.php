<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ListingPromotionSource;
use App\Enums\ListingPromotionStatus;
use App\Enums\MarketListingStatus;
use App\Enums\MonetizationPackageType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ListingPromotion;
use App\Models\MarketListing;
use App\Models\MonetizationPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ListingPromotionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeManage();

        $status = $request->string('status')->toString();

        if (! in_array($status, ['pending', 'active', 'expired', 'cancelled', 'all'], true)) {
            $status = 'pending';
        }

        $promotions = ListingPromotion::query()
            ->with(['user', 'listing', 'package', 'activator'])
            ->when($status !== 'all', function ($query) use ($status): void {
                if ($status === ListingPromotionStatus::Expired->value) {
                    $query->where(function ($query): void {
                        $query->where('status', ListingPromotionStatus::Expired)
                            ->orWhere(function ($query): void {
                                $query->where('status', ListingPromotionStatus::Active)
                                    ->where('ends_at', '<=', now());
                            });
                    });

                    return;
                }

                if ($status === ListingPromotionStatus::Active->value) {
                    $query->currentlyActive();

                    return;
                }

                $query->where('status', $status);
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.monetization.promotions.index', [
            'promotions' => $promotions,
            'status' => $status,
            'packages' => $this->promotionPackages(),
            'unpaidGrantsEnabled' => monetization_setting('admin_unpaid_grants_enabled', false),
        ]);
    }

    public function show(ListingPromotion $promotion): View
    {
        $this->authorizeManage();

        $promotion->load(['user', 'listing', 'package', 'activator']);

        return view('admin.monetization.promotions.show', [
            'promotion' => $promotion,
        ]);
    }

    public function activate(Request $request, ListingPromotion $promotion): RedirectResponse
    {
        $this->authorizeManage();
        abort_unless($promotion->status === ListingPromotionStatus::Pending, 403);

        if ($promotion->listing?->status !== MarketListingStatus::Published) {
            return back()->with('error', 'Only published listings can have an active promotion.');
        }

        if ($promotion->listing->promotions()->currentlyActive()->exists()) {
            return back()->with('error', 'This listing already has an active promotion.');
        }

        $promotion->activateFromSnapshot($request->user());

        return redirect()
            ->route('admin.monetization.promotions.index')
            ->with('success', 'Listing promotion was activated for testing.');
    }

    public function cancel(ListingPromotion $promotion): RedirectResponse
    {
        $this->authorizeManage();
        abort_unless(in_array($promotion->status, [ListingPromotionStatus::Pending, ListingPromotionStatus::Active], true), 403);

        $promotion->forceFill([
            'status' => ListingPromotionStatus::Cancelled,
        ])->save();

        return redirect()
            ->route('admin.monetization.promotions.index')
            ->with('success', 'Listing promotion was cancelled.');
    }

    public function grant(Request $request): RedirectResponse
    {
        $this->authorizeManage();

        if (! monetization_setting('admin_unpaid_grants_enabled', false)) {
            return back()->with('error', 'Unpaid listing promotion grants are disabled.');
        }

        $validated = $request->validate([
            'listing_id' => ['required', 'integer', 'exists:market_listings,id'],
            'package_id' => [
                'required',
                'integer',
                Rule::exists('monetization_packages', 'id')->where(function ($query): void {
                    $query->where('type', MonetizationPackageType::ListingPromotion->value)
                        ->whereNotNull('placement');
                }),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $package = MonetizationPackage::query()->findOrFail($validated['package_id']);

        if ($package->duration_days === null || $package->duration_days < 1 || blank($package->placement)) {
            return back()->with('error', 'That listing promotion package is not available.');
        }

        $result = DB::transaction(function () use ($request, $validated, $package): string {
            $listing = MarketListing::query()->whereKey($validated['listing_id'])->lockForUpdate()->firstOrFail();

            if ($listing->status !== MarketListingStatus::Published) {
                return 'unpublished';
            }

            if ($listing->hasActivePromotion()) {
                return 'active';
            }

            $listing->promotions()
                ->where('status', ListingPromotionStatus::Pending)
                ->with('order')
                ->get()
                ->each(function (ListingPromotion $promotion): void {
                    $promotion->order?->cancelIfPending();
                    $promotion->forceFill([
                        'status' => ListingPromotionStatus::Cancelled,
                        'notes' => 'Cancelled because an admin granted a listing promotion.',
                    ])->save();
                });

            $promotion = $listing->promotions()->create([
                'user_id' => $listing->user_id,
                'package_id' => $package->id,
                'status' => ListingPromotionStatus::Pending,
                'source' => ListingPromotionSource::AdminGrant,
                'placement' => $package->placement,
                'package_type' => $package->type->value,
                'package_name' => $package->name,
                'package_slug' => $package->slug,
                'duration_days' => $package->duration_days,
                'price' => $package->price,
                'currency' => $package->currency,
                'notes' => $validated['notes'] ?? 'Unpaid admin grant.',
            ]);

            $promotion->activateFromSnapshot($request->user());

            return 'granted';
        });

        return match ($result) {
            'unpublished' => back()->with('error', 'Only published listings can be promoted.'),
            'active' => back()->with('error', 'That listing already has an active promotion.'),
            default => redirect()
                ->route('admin.monetization.promotions.index')
                ->with('success', 'Listing promotion was granted.'),
        };
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->hasPermission(Permission::ManageMonetization), 403);
    }

    /**
     * @return Collection<int, MonetizationPackage>
     */
    private function promotionPackages(): Collection
    {
        return MonetizationPackage::query()
            ->where('type', MonetizationPackageType::ListingPromotion)
            ->orderBy('sort_order')
            ->get();
    }
}
