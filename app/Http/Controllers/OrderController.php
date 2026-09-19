<?php

namespace App\Http\Controllers;

use App\Actions\FulfillPaidCheckoutSession;
use App\Actions\StartStripeCheckout;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function show(Request $request, Order $order, FulfillPaidCheckoutSession $fulfillPaidCheckoutSession): View|RedirectResponse
    {
        abort_unless($request->user()?->id === $order->user_id, 403);

        if ($request->query('checkout') === 'success') {
            $order = $fulfillPaidCheckoutSession->handle(
                $order,
                $request->string('session_id')->toString() ?: null,
            );
        }

        $order->load(['package', 'payments', 'verification', 'boost.post', 'listingPromotion.listing']);

        if (
            $request->query('checkout') === 'success'
            && $order->status === OrderStatus::Paid
        ) {
            return $this->paidCheckoutRedirect($order);
        }

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

        $order->cancelIfPending();

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'The pending order was cancelled.');
    }

    private function paidCheckoutRedirect(Order $order): RedirectResponse
    {
        $listing = $order->listingPromotion?->listing;

        if ($listing !== null) {
            $until = $order->listingPromotion?->ends_at?->toFormattedDateString();

            return redirect()
                ->route('market.show', $listing)
                ->with('success', $until !== null
                    ? 'Payment confirmed. Promoted until '.$until.'.'
                    : 'Payment confirmed. Your listing is now promoted.');
        }

        $post = $order->boost?->post;

        if ($post !== null) {
            return redirect()
                ->route('posts.show', $post)
                ->with('success', 'Payment confirmed. Your post boost is now active.');
        }

        return redirect()
            ->route('orders.show', $order)
            ->with('success', 'Payment confirmed. This order is paid.');
    }
}
