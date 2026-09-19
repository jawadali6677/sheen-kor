<?php

namespace App\Actions;

use App\Enums\ListingPromotionStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PostBoostStatus;
use App\Enums\UserVerificationStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class FailMonetizationOrder
{
    /**
     * @param  array<string, mixed>  $paymentPayload
     */
    public function handle(Order $order, array $paymentPayload = []): Order
    {
        return DB::transaction(function () use ($order, $paymentPayload): Order {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null) {
                return $order;
            }

            if ($locked->status === OrderStatus::Paid) {
                return $locked;
            }

            if ($locked->status !== OrderStatus::Pending) {
                return $locked;
            }

            $locked->expireStripeCheckoutSessions();

            $locked->payments()
                ->where('status', PaymentStatus::Pending)
                ->get()
                ->each(function ($payment) use ($paymentPayload): void {
                    $payload = $payment->payload ?? [];
                    $incoming = $paymentPayload;

                    if (isset($incoming['stripe_event_ids'])) {
                        $incoming['stripe_event_ids'] = array_values(array_unique(array_merge(
                            $payload['stripe_event_ids'] ?? [],
                            $incoming['stripe_event_ids'],
                        )));
                    }

                    $payment->forceFill([
                        'status' => PaymentStatus::Failed,
                        'payload' => array_merge($payload, $incoming),
                    ])->save();
                });

            $locked->forceFill([
                'status' => OrderStatus::Failed,
            ])->save();

            $locked->load(['verification', 'boost', 'listingPromotion']);

            if ($locked->verification?->status === UserVerificationStatus::PendingPayment) {
                $locked->verification->forceFill([
                    'status' => UserVerificationStatus::Cancelled,
                ])->save();
            }

            if ($locked->boost?->status === PostBoostStatus::Pending) {
                $locked->boost->forceFill([
                    'status' => PostBoostStatus::Cancelled,
                ])->save();
            }

            if ($locked->listingPromotion?->status === ListingPromotionStatus::Pending) {
                $locked->listingPromotion->forceFill([
                    'status' => ListingPromotionStatus::Cancelled,
                ])->save();
            }

            return $locked->fresh([
                'verification',
                'boost',
                'listingPromotion',
                'payments',
            ]);
        });
    }
}
