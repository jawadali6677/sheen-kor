<?php

namespace App\Http\Controllers;

use App\Actions\CreateMonetizationOrder;
use App\Actions\StartStripeCheckout;
use App\Enums\ListingPromotionStatus;
use App\Enums\MonetizationPackageType;
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
    public function create(MarketListing $listing): View
    {
        $this->authorize('promote', $listing);

        return view('market.promote', [
            'listing' => $listing,
            'packages' => $this->enabledPromotionPackages(),
            'openPromotion' => $listing->promotions()
                ->with('order')
                ->where(function ($query): void {
                    $query->currentlyActive()
                        ->orWhere('status', ListingPromotionStatus::Pending);
                })
                ->latest('id')
                ->first(),
        ]);
    }

    public function store(Request $request, MarketListing $listing, CreateMonetizationOrder $createOrder, StartStripeCheckout $startStripeCheckout): RedirectResponse
    {
        $this->authorize('promote', $listing);

        $validated = $request->validate([
            'package_id' => [
                'required',
                'integer',
                Rule::exists('monetization_packages', 'id')->where(function ($query): void {
                    $query->where('type', MonetizationPackageType::ListingPromotion->value)
                        ->where('is_enabled', true)
                        ->whereNotNull('placement');
                }),
            ],
        ]);

        $package = MonetizationPackage::query()->findOrFail($validated['package_id']);

        if ($package->duration_days === null || $package->duration_days < 1 || blank($package->placement)) {
            return back()->with('error', 'That listing promotion package is not available.');
        }

        $order = $createOrder->handle($request->user(), $package, listing: $listing);

        if ($order === null) {
            return back()->with('error', 'This listing already has a pending or active promotion, or it cannot be promoted.');
        }

        return $startStripeCheckout->redirect($order);
    }

    public function destroy(Request $request, ListingPromotion $promotion): RedirectResponse
    {
        abort_unless($request->user()?->id === $promotion->user_id, 403);
        abort_unless($promotion->status === ListingPromotionStatus::Pending, 403);

        $listing = $promotion->listing;

        DB::transaction(function () use ($promotion): void {
            $promotion->order?->cancelIfPending();

            $locked = ListingPromotion::query()->whereKey($promotion->id)->lockForUpdate()->first();

            if ($locked?->status === ListingPromotionStatus::Pending) {
                $locked->forceFill([
                    'status' => ListingPromotionStatus::Cancelled,
                ])->save();
            }
        });

        if ($listing === null) {
            return redirect()
                ->route('market.mine')
                ->with('success', 'The pending promotion was cancelled.');
        }

        return redirect()
            ->route('market.show', $listing)
            ->with('success', 'The pending promotion was cancelled.');
    }

    /**
     * @return Collection<int, MonetizationPackage>
     */
    private function enabledPromotionPackages()
    {
        return MonetizationPackage::query()
            ->where('type', MonetizationPackageType::ListingPromotion)
            ->where('is_enabled', true)
            ->whereNotNull('placement')
            ->where('duration_days', '>=', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
