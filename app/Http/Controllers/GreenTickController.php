<?php

namespace App\Http\Controllers;

use App\Actions\CreateMonetizationOrder;
use App\Actions\StartStripeCheckout;
use App\Enums\MonetizationPackageType;
use App\Enums\UserVerificationStatus;
use App\Models\MonetizationPackage;
use App\Models\UserVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GreenTickController extends Controller
{
    public function store(Request $request, CreateMonetizationOrder $createOrder, StartStripeCheckout $startStripeCheckout): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $eligibility = $user->greenTickEligibility();

        if (! $eligibility['eligible']) {
            return back()->with('error', 'You do not meet the Green Tick eligibility requirements yet.');
        }

        $validated = $request->validate([
            'package_id' => [
                'required',
                'integer',
                Rule::exists('monetization_packages', 'id')->where(function ($query) {
                    $query->where('type', MonetizationPackageType::GreenTick->value)
                        ->where('is_enabled', true);
                }),
            ],
        ]);

        $package = MonetizationPackage::query()->findOrFail($validated['package_id']);

        $order = $createOrder->handle($user, $package);

        if ($order === null) {
            return back()->with('error', 'You already have a pending or active Green Tick.');
        }

        return $startStripeCheckout->redirect($order);
    }

    public function destroy(Request $request, UserVerification $verification): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $verification->user_id === $user->id, 403);
        abort_unless(in_array($verification->status, [
            UserVerificationStatus::PendingPayment,
            UserVerificationStatus::PendingReview,
        ], true), 403);

        $verification->order?->cancelIfPending();

        if (in_array($verification->fresh()?->status, [
            UserVerificationStatus::PendingPayment,
            UserVerificationStatus::PendingReview,
        ], true)) {
            $verification->forceFill([
                'status' => UserVerificationStatus::Cancelled,
            ])->save();
        }

        return back()->with('success', 'Your Green Tick request was cancelled.');
    }
}
