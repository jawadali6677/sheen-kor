<?php

namespace App\Http\Controllers;

use App\Actions\PlaceFeedAds;
use App\Enums\AdEventType;
use App\Enums\AdPlacement;
use App\Models\AdEvent;
use App\Models\Advertisement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdEventController extends Controller
{
    public function storeImpression(Request $request, Advertisement $advertisement, PlaceFeedAds $placeFeedAds): JsonResponse
    {
        abort_unless($advertisement->is_enabled, 404);

        $validated = $request->validate([
            'placement' => ['required', Rule::enum(AdPlacement::class)],
        ]);

        AdEvent::query()->create([
            'advertisement_id' => $advertisement->id,
            'user_id' => $request->user()?->id,
            'visitor_key' => $placeFeedAds->visitorKey($request),
            'type' => AdEventType::Impression,
            'placement' => $validated['placement'],
        ]);

        return response()->json(['recorded' => true]);
    }

    public function click(Request $request, Advertisement $advertisement, PlaceFeedAds $placeFeedAds): RedirectResponse
    {
        abort_unless($advertisement->is_enabled, 404);

        $placement = AdPlacement::tryFrom($request->string('placement')->toString()) ?? AdPlacement::FeedPosts;

        AdEvent::query()->create([
            'advertisement_id' => $advertisement->id,
            'user_id' => $request->user()?->id,
            'visitor_key' => $placeFeedAds->visitorKey($request),
            'type' => AdEventType::Click,
            'placement' => $placement,
        ]);

        abort_unless(
            Str::startsWith($advertisement->destination_url, ['https://', 'http://']),
            404,
        );

        return redirect()->away($advertisement->destination_url);
    }
}
