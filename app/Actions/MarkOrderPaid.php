<?php

namespace App\Actions;

use App\Enums\ListingPromotionStatus;
use App\Enums\MarketListingStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\PostBoostStatus;
use App\Enums\UserVerificationStatus;
use App\Models\Order;
use App\Models\User;
use App\Notifications\GreenTickNeedsReview;
use Illuminate\Support\Facades\DB;

class MarkOrderPaid
{
    /**
     * @param  array<string, mixed>  $paymentPayload
     */
    public function handle(Order $order, ?User $admin = null, array $paymentPayload = []): Order
    {
        return DB::transaction(function () use ($order, $admin, $paymentPayload): Order {
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

            $locked->payments()
                ->where('status', PaymentStatus::Pending)
                ->get()
                ->each(function ($payment) use ($admin, $paymentPayload): void {
                    $payload = $this->mergePaymentPayload($payment->payload ?? [], $paymentPayload);

                    if ($admin !== null) {
                        $payload['marked_paid_by'] = $admin->id;
                        $payload['marked_paid_at'] = now()->toIso8601String();
                    }

                    $payment->forceFill([
                        'status' => PaymentStatus::Paid,
                        'payload' => $payload,
                    ])->save();
                });

            $locked->forceFill([
                'status' => OrderStatus::Paid,
            ])->save();

            $this->applyEntitlement($locked, $admin);

            return $locked->fresh([
                'verification',
                'boost',
                'listingPromotion',
                'payments',
            ]);
        });
    }

    private function applyEntitlement(Order $order, ?User $admin): void
    {
        $order->load(['verification', 'boost.post', 'listingPromotion.listing']);

        if ($order->boost !== null && $order->boost->status === PostBoostStatus::Pending) {
            if ($order->boost->post?->status !== 'published') {
                return;
            }

            $order->boost->activateFromSnapshot($admin);

            return;
        }

        if ($order->listingPromotion !== null && $order->listingPromotion->status === ListingPromotionStatus::Pending) {
            if ($order->listingPromotion->listing?->status !== MarketListingStatus::Published) {
                return;
            }

            $order->listingPromotion->activateFromSnapshot($admin);

            return;
        }

        if ($order->verification !== null && $order->verification->status === UserVerificationStatus::PendingPayment) {
            if (monetization_setting('green_tick_requires_review', true)) {
                $order->verification->forceFill([
                    'status' => UserVerificationStatus::PendingReview,
                ])->save();

                $order->verification->load('user');

                User::query()
                    ->withPermission(Permission::ManageMonetization)
                    ->where('id', '!=', $order->user_id)
                    ->get()
                    ->each(fn (User $reviewer) => $reviewer->notify(new GreenTickNeedsReview($order->verification)));

                return;
            }

            $order->verification->activateFromSnapshot();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergePaymentPayload(array $payload, array $incoming): array
    {
        if (isset($incoming['stripe_event_ids'])) {
            $incoming['stripe_event_ids'] = array_values(array_unique(array_merge(
                $payload['stripe_event_ids'] ?? [],
                $incoming['stripe_event_ids'],
            )));
        }

        return array_merge($payload, $incoming);
    }
}
