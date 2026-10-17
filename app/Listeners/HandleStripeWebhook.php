<?php

namespace App\Listeners;

use App\Actions\FailMonetizationOrder;
use App\Actions\FulfillStripeCheckoutSession;
use App\Contracts\StripeCheckoutGateway;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StripeCheckoutFulfillmentOutcome;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

class HandleStripeWebhook
{
    public function __construct(
        public FailMonetizationOrder $failMonetizationOrder,
        public FulfillStripeCheckoutSession $fulfillStripeCheckoutSession,
        public StripeCheckoutGateway $stripeCheckout,
    ) {}

    public function handle(WebhookReceived $event): void
    {
        $type = $event->payload['type'] ?? '';

        match ($type) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded' => $this->fulfillCheckout($event->payload),
            'checkout.session.expired',
            'checkout.session.async_payment_failed' => $this->failCheckout($event->payload),
            'payment_intent.payment_failed' => $this->recordDeclinedAttempt($event->payload),
            default => $this->ignoreWebhook($event->payload),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordDeclinedAttempt(array $payload): void
    {
        $object = $this->objectFromPayload($payload);
        ['order' => $order, 'matched_by' => $matchedBy] = $this->orderFromPayload($payload);

        if ($order === null) {
            $this->logSkip(
                $payload,
                $object,
                null,
                $matchedBy,
                $matchedBy === 'session_order_mismatch' ? 'session_order_mismatch' : 'order_not_found',
            );

            return;
        }

        if ($order->status === OrderStatus::Paid) {
            $this->logSkip($payload, $object, $order, $matchedBy, 'order_already_paid');

            return;
        }

        if ($order->status !== OrderStatus::Pending) {
            $this->logSkip($payload, $object, $order, $matchedBy, 'order_not_pending');

            return;
        }

        $eventId = $this->eventId($payload);

        if ($eventId !== null && $this->alreadyProcessed($order, $eventId)) {
            $this->logSkip($payload, $object, $order, $matchedBy, 'already_processed');

            return;
        }

        $recorded = DB::transaction(function () use ($order, $object, $eventId): bool {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status !== OrderStatus::Pending) {
                return false;
            }

            if ($eventId !== null && $this->alreadyProcessed($locked, $eventId)) {
                return false;
            }

            $payment = $locked->payments()
                ->where('status', PaymentStatus::Pending)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $payment instanceof Payment) {
                return false;
            }

            $existing = $payment->payload ?? [];
            $attempts = is_array($existing['declined_attempts'] ?? null) ? $existing['declined_attempts'] : [];
            $paymentIntentId = $this->paymentIntentId($object);
            $attempt = array_filter([
                'event_id' => $eventId,
                'payment_intent' => $paymentIntentId,
                ...$this->failureDetail($object),
            ], fn (mixed $value): bool => is_string($value) && $value !== '');
            $attempts[] = $attempt;

            $eventIds = is_array($existing['stripe_event_ids'] ?? null) ? $existing['stripe_event_ids'] : [];

            if ($eventId !== null) {
                $eventIds[] = $eventId;
            }

            $next = $existing;
            $next['declined_attempts'] = array_values($attempts);
            $next['stripe_event_ids'] = array_values(array_unique($eventIds));

            if (is_string($paymentIntentId) && $paymentIntentId !== '') {
                $next['payment_intent'] = $paymentIntentId;
            }

            $payment->forceFill([
                'payload' => $next,
            ])->save();

            return true;
        });

        if (! $recorded) {
            $this->logSkip($payload, $object, $order->fresh(), $matchedBy, 'order_not_pending');
        }
    }

    /**
     * @param  array<string, mixed>  $object
     * @return array<string, string>
     */
    private function failureDetail(array $object): array
    {
        $error = $object['last_payment_error'] ?? null;

        if (! is_array($error)) {
            return [];
        }

        $message = $error['message'] ?? null;

        if (is_string($message) && strlen($message) > 500) {
            $message = substr($message, 0, 500);
        }

        $detail = [];

        foreach (['code', 'decline_code'] as $key) {
            if (is_string($error[$key] ?? null) && $error[$key] !== '') {
                $detail[$key] = $error[$key];
            }
        }

        if (is_string($message) && $message !== '') {
            $detail['message'] = $message;
        }

        return $detail;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fulfillCheckout(array $payload): void
    {
        $object = $this->objectFromPayload($payload);
        ['order' => $order, 'matched_by' => $matchedBy] = $this->orderFromPayload($payload);

        if ($order === null) {
            $this->logSkip(
                $payload,
                $object,
                null,
                $matchedBy,
                $matchedBy === 'session_order_mismatch' ? 'session_order_mismatch' : 'order_not_found',
            );

            return;
        }

        if ($order->status === OrderStatus::Paid) {
            $this->logSkip($payload, $object, $order, $matchedBy, 'order_already_paid');

            return;
        }

        if ($order->status !== OrderStatus::Pending) {
            $this->logSkip($payload, $object, $order, $matchedBy, 'order_not_pending');

            return;
        }

        $eventId = $this->eventId($payload);

        if ($eventId !== null && $this->alreadyProcessed($order, $eventId)) {
            $this->logSkip($payload, $object, $order, $matchedBy, 'already_processed');

            return;
        }

        $sessionId = $this->checkoutSessionId($object);

        if ($sessionId === null) {
            $this->logSkip($payload, $object, $order, $matchedBy, 'missing_session_id');

            return;
        }

        $result = $this->fulfillStripeCheckoutSession->handle(
            $order,
            $sessionId,
            $this->paymentPayload($payload, $object),
        );
        $updated = $result['order'];

        if ($result['outcome'] === StripeCheckoutFulfillmentOutcome::Fulfilled) {
            Log::info('Stripe webhook fulfilled', $this->logContext($payload, $object, $updated, $matchedBy) + [
                'reason' => 'fulfilled',
            ]);

            return;
        }

        $this->logSkip($payload, $object, $updated, $matchedBy, $this->fulfillmentSkipReason($result['outcome']));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function failCheckout(array $payload): void
    {
        $object = $this->objectFromPayload($payload);
        ['order' => $order, 'matched_by' => $matchedBy] = $this->orderFromPayload($payload);

        if ($order === null) {
            $this->logSkip(
                $payload,
                $object,
                null,
                $matchedBy,
                $matchedBy === 'session_order_mismatch' ? 'session_order_mismatch' : 'order_not_found',
            );

            return;
        }

        if ($order->status === OrderStatus::Paid) {
            $this->logSkip($payload, $object, $order, $matchedBy, 'order_already_paid');

            return;
        }

        if ($order->status !== OrderStatus::Pending) {
            $this->logSkip($payload, $object, $order, $matchedBy, 'order_not_pending');

            return;
        }

        $eventId = $this->eventId($payload);

        if ($eventId !== null && $this->alreadyProcessed($order, $eventId)) {
            $this->logSkip($payload, $object, $order, $matchedBy, 'already_processed');

            return;
        }

        if ($this->isStaleCheckoutFailure($order, $object)) {
            return;
        }

        $sessionId = $this->checkoutSessionId($object);

        if ($sessionId === null) {
            $this->logSkip($payload, $object, $order, $matchedBy, 'missing_session_id');

            return;
        }

        $session = $this->stripeCheckout->retrieveCheckoutSession($sessionId);

        if (! is_array($session)) {
            $this->logSkip($payload, $object, $order, $matchedBy, 'session_not_retrieved');

            return;
        }

        $result = $this->fulfillStripeCheckoutSession->handle(
            $order,
            $sessionId,
            $this->paymentPayload($payload, $object),
        );

        if (in_array($result['outcome'], [
            StripeCheckoutFulfillmentOutcome::Fulfilled,
            StripeCheckoutFulfillmentOutcome::AlreadyPaid,
        ], true)) {
            Log::info('Stripe webhook fulfilled', $this->logContext($payload, $object, $result['order'], $matchedBy) + [
                'reason' => 'fulfilled',
            ]);

            return;
        }

        if ($result['outcome'] !== StripeCheckoutFulfillmentOutcome::Unpaid) {
            $this->logSkip($payload, $object, $result['order'], $matchedBy, $this->fulfillmentSkipReason($result['outcome']));

            return;
        }

        $type = (string) ($payload['type'] ?? '');
        $status = (string) ($session['status'] ?? '');

        if ($type === 'checkout.session.expired' && $status !== 'expired') {
            $this->logSkip($payload, $object, $order, $matchedBy, 'session_still_open');

            return;
        }

        $updated = $this->failMonetizationOrder->handle($order, $this->paymentPayload($payload, $object));

        Log::info('Stripe webhook marked order failed', $this->logContext($payload, $object, $updated, $matchedBy) + [
            'reason' => 'checkout_failed',
            'order_status' => $updated->status->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function isStaleCheckoutFailure(Order $order, array $object): bool
    {
        $current = $order->payments()
            ->where('status', PaymentStatus::Pending)
            ->latest('id')
            ->first();

        if (! $current instanceof Payment) {
            return false;
        }

        $sessionId = ($object['object'] ?? null) === 'checkout.session'
            ? ($object['id'] ?? null)
            : null;

        if (is_string($sessionId) && $sessionId !== '') {
            $activeSession = $current->stripeCheckoutSessionId();

            if ($activeSession !== null) {
                return $activeSession !== $sessionId;
            }
        }

        $paymentIntent = $object['payment_intent'] ?? (($object['object'] ?? null) === 'payment_intent' ? ($object['id'] ?? null) : null);

        if (is_array($paymentIntent)) {
            $paymentIntent = $paymentIntent['id'] ?? null;
        }

        if (! is_string($paymentIntent) || $paymentIntent === '') {
            return false;
        }

        $currentIntent = $current->payload['payment_intent'] ?? null;

        if (is_string($currentIntent) && $currentIntent !== '') {
            return $currentIntent !== $paymentIntent;
        }

        $previousSessions = $current->payload['previous_checkout_session_ids'] ?? [];

        return is_array($previousSessions) && $previousSessions !== [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ignoreWebhook(array $payload): void
    {
        $type = (string) ($payload['type'] ?? '');

        if (! str_starts_with($type, 'checkout.') && ! str_starts_with($type, 'payment_intent.')) {
            return;
        }

        Log::info('Stripe webhook skipped', [
            'event_id' => $this->eventId($payload),
            'event_type' => $type,
            'reason' => 'event_not_handled',
        ]);
    }

    /**
     * Metadata and the stored Checkout Session must name the same order.
     * A payload that points at two different orders is ignored.
     *
     * @param  array<string, mixed>  $payload
     * @return array{order: ?Order, matched_by: ?string}
     */
    private function orderFromPayload(array $payload): array
    {
        $object = $this->objectFromPayload($payload);
        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
        $metadataOrder = $this->findOrderByIdentifier($metadata['order_id'] ?? null);
        $referenceOrder = $this->findOrderByIdentifier($object['client_reference_id'] ?? null);
        $sessionOrder = $this->orderFromCheckoutSession($object);
        $paymentIntentOrder = $this->orderFromPaymentIntent($object);

        $matches = array_values(array_filter([
            $metadataOrder,
            $referenceOrder,
            $sessionOrder,
            $paymentIntentOrder,
        ]));
        $orderIds = array_values(array_unique(array_map(
            fn (Order $order): int => $order->id,
            $matches,
        )));

        if (count($orderIds) > 1) {
            return ['order' => null, 'matched_by' => 'session_order_mismatch'];
        }

        if ($metadataOrder !== null) {
            return ['order' => $metadataOrder, 'matched_by' => 'metadata'];
        }

        if ($referenceOrder !== null) {
            return ['order' => $referenceOrder, 'matched_by' => 'client_reference_id'];
        }

        if ($sessionOrder !== null) {
            return ['order' => $sessionOrder, 'matched_by' => 'checkout_session'];
        }

        if ($paymentIntentOrder !== null) {
            return ['order' => $paymentIntentOrder, 'matched_by' => 'payment_intent'];
        }

        return ['order' => null, 'matched_by' => null];
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function orderFromCheckoutSession(array $object): ?Order
    {
        $sessionId = ($object['object'] ?? null) === 'checkout.session' ? ($object['id'] ?? null) : null;

        if (! is_string($sessionId) || $sessionId === '') {
            return null;
        }

        return Payment::query()->where('provider_reference', $sessionId)->first()?->order;
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function orderFromPaymentIntent(array $object): ?Order
    {
        $paymentIntent = $object['payment_intent'] ?? null;

        if (($object['object'] ?? null) === 'payment_intent') {
            $paymentIntent = $object['id'] ?? $paymentIntent;
        }

        if (is_array($paymentIntent)) {
            $paymentIntent = $paymentIntent['id'] ?? null;
        }

        if (! is_string($paymentIntent) || $paymentIntent === '') {
            return null;
        }

        return Payment::query()
            ->where(function ($query) use ($paymentIntent): void {
                $query->where('payload->payment_intent', $paymentIntent)
                    ->orWhere('provider_reference', $paymentIntent);
            })
            ->first()
            ?->order;
    }

    private function findOrderByIdentifier(mixed $orderId): ?Order
    {
        if (is_int($orderId)) {
            return Order::query()->find($orderId);
        }

        if (! is_string($orderId)) {
            return null;
        }

        $orderId = trim($orderId);

        if ($orderId === '' || ! ctype_digit($orderId)) {
            return null;
        }

        return Order::query()->find((int) $orderId);
    }

    private function alreadyProcessed(Order $order, string $eventId): bool
    {
        return $order->payments()
            ->get()
            ->contains(function (Payment $payment) use ($eventId): bool {
                $eventIds = $payment->payload['stripe_event_ids'] ?? [];

                if (! is_array($eventIds)) {
                    return false;
                }

                return in_array($eventId, $eventIds, true);
            });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function paymentPayload(array $payload, array $object): array
    {
        $eventId = $this->eventId($payload);
        $paymentIntent = $object['payment_intent'] ?? null;

        if (is_array($paymentIntent)) {
            $paymentIntent = $paymentIntent['id'] ?? null;
        }

        $eventIds = $eventId !== null ? [$eventId] : [];

        return array_filter([
            'stripe_event_ids' => $eventIds,
            'checkout_session_id' => ($object['object'] ?? null) === 'checkout.session' ? ($object['id'] ?? null) : null,
            'payment_intent' => is_string($paymentIntent) ? $paymentIntent : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function checkoutSessionId(array $object): ?string
    {
        if (($object['object'] ?? null) !== 'checkout.session') {
            return null;
        }

        $sessionId = $object['id'] ?? null;

        return is_string($sessionId) && $sessionId !== '' ? $sessionId : null;
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function paymentIntentId(array $object): ?string
    {
        $paymentIntent = $object['payment_intent'] ?? null;

        if (($object['object'] ?? null) === 'payment_intent') {
            $paymentIntent = $object['id'] ?? $paymentIntent;
        }

        if (is_array($paymentIntent)) {
            $paymentIntent = $paymentIntent['id'] ?? null;
        }

        return is_string($paymentIntent) && $paymentIntent !== '' ? $paymentIntent : null;
    }

    private function fulfillmentSkipReason(StripeCheckoutFulfillmentOutcome $outcome): string
    {
        return match ($outcome) {
            StripeCheckoutFulfillmentOutcome::AlreadyPaid => 'order_already_paid',
            StripeCheckoutFulfillmentOutcome::NotPending => 'order_not_pending',
            StripeCheckoutFulfillmentOutcome::Unpaid => 'payment_not_confirmed',
            StripeCheckoutFulfillmentOutcome::SessionMismatch => 'session_order_mismatch',
            StripeCheckoutFulfillmentOutcome::SessionNotLinked => 'session_not_linked',
            StripeCheckoutFulfillmentOutcome::AwaitingWebhook => 'session_not_retrieved',
            StripeCheckoutFulfillmentOutcome::Fulfilled => 'fulfilled',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function objectFromPayload(array $payload): array
    {
        $object = $payload['data']['object'] ?? [];

        return is_array($object) ? $object : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function eventId(array $payload): ?string
    {
        return is_string($payload['id'] ?? null) ? $payload['id'] : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $object
     * @return array<string, mixed>
     */
    private function logContext(array $payload, array $object, ?Order $order, ?string $matchedBy): array
    {
        $metadata = is_array($object['metadata'] ?? null) ? $object['metadata'] : [];
        $metadataOrderId = $metadata['order_id'] ?? null;
        $clientReferenceId = $object['client_reference_id'] ?? null;
        $sessionId = ($object['object'] ?? null) === 'checkout.session' ? ($object['id'] ?? null) : null;

        return array_filter([
            'event_id' => $this->eventId($payload),
            'event_type' => is_string($payload['type'] ?? null) ? $payload['type'] : null,
            'order_id' => $order?->id,
            'matched_by' => $matchedBy,
            'metadata_order_id' => is_scalar($metadataOrderId) ? (string) $metadataOrderId : null,
            'client_reference_id' => is_scalar($clientReferenceId) ? (string) $clientReferenceId : null,
            'session_id' => is_string($sessionId) ? $sessionId : null,
            'payment_status' => is_scalar($object['payment_status'] ?? null) ? (string) $object['payment_status'] : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $object
     */
    private function logSkip(array $payload, array $object, ?Order $order, ?string $matchedBy, string $reason): void
    {
        Log::info('Stripe webhook skipped', $this->logContext($payload, $object, $order, $matchedBy) + [
            'reason' => $reason,
        ]);
    }
}
