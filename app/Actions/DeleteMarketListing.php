<?php

namespace App\Actions;

use App\Exceptions\ContentWriteFailed;
use App\Models\MarketListing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeleteMarketListing
{
    public function handle(MarketListing $listing): void
    {
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
        } catch (Throwable $exception) {
            DB::rollBack();

            report($exception);

            throw new ContentWriteFailed('Something went wrong while deleting your listing.', previous: $exception);
        }
    }
}
