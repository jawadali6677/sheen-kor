<?php

namespace App\Http\Controllers;

use App\Actions\EvaluateVideoInterstitial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VideoInterstitialController extends Controller
{
    public function store(Request $request, EvaluateVideoInterstitial $evaluateVideoInterstitial): JsonResponse
    {
        $validated = $request->validate([
            'source_type' => ['required', Rule::in(['post', 'alert'])],
            'source_id' => ['required', 'integer', 'min:1'],
            'duration_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
        ]);

        $result = $evaluateVideoInterstitial->handle(
            $request,
            $validated['source_type'],
            (int) $validated['source_id'],
            (int) $validated['duration_seconds'],
        );

        return response()->json($result);
    }
}
