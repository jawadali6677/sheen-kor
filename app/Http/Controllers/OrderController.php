<?php

namespace App\Http\Controllers;

use App\Actions\StartStripeCheckout;
use App\Enums\ListingPromotionStatus;
use App\Enums\OrderStatus;
use App\Enums\PostBoostStatus;
use App\Enums\UserVerificationStatus;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function show(Request $request, Order $order): View
    {
        abort_unless($request->user()?->id === $order->user_id, 403);

        $order->load(['package', 'payments', 'verification', 'boost.post', 'listingPromotion.listing']);

        return view('orders.show', [
            'order' => $order,
        ]);
    }

    public function pay(Request $request, Order $order, StartStripeCheckout $startStripeCheckout): RedirectResponse
    {
        abort_unless($request->user()?->id === $order->user_id, 403);
        abort_unless($order->status === OrderStatus::Pending, 403);

        $checkoutUrl = $startStripeCheckout->handle($order);

        if (filled($checkoutUrl)) {
            return redirect()->away($checkoutUrl);
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('error', 'Checkout could not be started. You can retry payment, or an admin can mark the order paid.');
    }

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()?->id === $order->user_id, 403);
        abort_unless($order->status === OrderStatus::Pending, 403);

        $order->load(['verification', 'boost', 'listingPromotion']);

        $order->cancelIfPending();

        if ($order->verification?->status === UserVerificationStatus::PendingPayment) {
            $order->verification->forceFill([
                'status' => UserVerificationStatus::Cancelled,
            ])->save();
        }

        if ($order->boost?->status === PostBoostStatus::Pending) {
            $order->boost->forceFill([
                'status' => PostBoostStatus::Cancelled,
            ])->save();
        }

        if ($order->listingPromotion?->status === ListingPromotionStatus::Pending) {
            $order->listingPromotion->forceFill([
                'status' => ListingPromotionStatus::Cancelled,
            ])->save();
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'The pending order was cancelled.');
    }
}
