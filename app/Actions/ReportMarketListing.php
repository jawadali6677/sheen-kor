<?php

namespace App\Actions;

use App\Enums\MarketListingReportReason;
use App\Models\MarketListing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportMarketListing
{
    /**
     * Returns false when this member already reported the listing.
     */
    public function handle(Request $request, MarketListing $listing): bool
    {
        if ($listing->reports()->where('user_id', $request->user()->id)->exists()) {
            return false;
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

        return true;
    }
}
