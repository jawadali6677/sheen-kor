<?php

namespace App\Actions;

use App\Contracts\StripeCheckoutGateway;
use App\Enums\OrderStatus;
use App\Enums\StripeCheckoutFulfillmentOutcome;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class FulfillStripeCheckoutSession
{
    public function __construct(
        public StripeCheckoutGateway $stripeCheckout,
        public MarkOrderPaid $markOrderPaid,
    ) {}

    /**
     * Mark a pending order paid when Stripe says the Checkout Session for this order is settled.
     *
     * @return array{order: Order, outcome: StripeCheckoutFulfillmentOutcome}
     */
    public function handle(Order $order, string $sessionId): array
    {
        $order->refresh();
        $sessionId = trim($sessionId);

        if ($order->status === OrderStatus::Paid) {
            $this->logSkip($order, $sessionId, 'already_paid');

            return $this->result($order, StripeCheckoutFulfillmentOutcome::AlreadyPaid);
        }

        if ($order->status !== OrderStatus::Pending) {
            $this->logSkip($order, $sessionId, 'order_not_pending');

            return $this->result($order, StripeCheckoutFulfillmentOutcome::NotPending);
        }

        if ($sessionId === '') {
            $this->logSkip($order, $sessionId, 'missing_session_id');

            return $this->result($order, StripeCheckoutFulfillmentOutcome::AwaitingWebhook);
        }

        if (! $this->sessionIdIsValid($sessionId)) {
            $this->logSkip($order, $this->sessionIdForLog($sessionId), 'invalid_session_id');

            return $this->result($order, StripeCheckoutFulfillmentOutcome::AwaitingWebhook);
        }

        $session = $this->stripeCheckout->retrieveCheckoutSession($sessionId);

        if ($session === null) {
            $this->logSkip($order, $sessionId, 'session_not_retrieved');

            return $this->result($order, StripeCheckoutFulfillmentOutcome::AwaitingWebhook);
        }

        $returnedSessionId = is_string($session['id'] ?? null) ? $session['id'] : '';

        if ($returnedSessionId !== $sessionId) {
            $this->logSkip($order, $sessionId, 'session_id_mismatch');

            return $this->result($order, StripeCheckoutFulfillmentOutcome::SessionNotLinked);
        }

        if ($this->sessionConflictsWithOrder($order, $session)) {
            $this->logSkip($order, $sessionId, 'session_order_mismatch', $session);

            return $this->result($order, StripeCheckoutFulfillmentOutcome::SessionMismatch);
        }

        if (! $this->sessionIsLinkedToOrder($order, $session)) {
            $this->logSkip($order, $sessionId, 'session_not_linked', $session);

            return $this->result($order, StripeCheckoutFulfillmentOutcome::SessionNotLinked);
        }

        $paymentStatus = (string) ($session['payment_status'] ?? '');

        if (! $this->paymentStatusIsSettled($paymentStatus)) {
            $this->logSkip($order, $sessionId, 'payment_not_confirmed', $session);

            return $this->result($order, StripeCheckoutFulfillmentOutcome::Unpaid);
        }

        $updated = $this->markOrderPaid->handle($order, paymentPayload: $this->paymentPayload($session));

        if ($updated->status !== OrderStatus::Paid) {
            $this->logSkip($updated, $sessionId, 'order_not_pending', $session);

            return $this->result($updated, StripeCheckoutFulfillmentOutcome::NotPending);
        }

        Log::info('Stripe checkout return fulfilled', [
            'order_id' => $updated->id,
            'session_id' => $sessionId,
            'payment_status' => $paymentStatus,
            'reason' => 'fulfilled',
        ]);

        return $this->result($updated, StripeCheckoutFulfillmentOutcome::Fulfilled);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function sessionConflictsWithOrder(Order $order, array $session): bool
    {
        foreach ($this->sessionOrderIds($session) as $sessionOrderId) {
            if ($sessionOrderId !== (string) $order->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function sessionIsLinkedToOrder(Order $order, array $session): bool
    {
        if ($this->sessionOrderIds($session) !== []) {
            return true;
        }

        $sessionId = (string) ($session['id'] ?? '');
        $order->loadMissing('payments');

        return $order->payments->contains(function (Payment $payment) use ($sessionId): bool {
            return $payment->provider_reference === $sessionId
                || ($payment->payload['checkout_session_id'] ?? null) === $sessionId;
        });
    }

    /**
     * @param  array<string, mixed>  $session
     * @return list<string>
     */
    private function sessionOrderIds(array $session): array
    {
        $identifiers = [];

        foreach ([$session['order_id'] ?? null, $session['client_reference_id'] ?? null] as $candidate) {
            $normalized = $this->normalizeOrderId($candidate);

            if ($normalized !== null) {
                $identifiers[] = $normalized;
            }
        }

        return array_values(array_unique($identifiers));
    }

    private function normalizeOrderId(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (! ctype_digit($value)) {
            return 'invalid:'.$value;
        }

        return (string) (int) $value;
    }

    private function paymentStatusIsSettled(string $paymentStatus): bool
    {
        return in_array($paymentStatus, ['paid', 'no_payment_required'], true);
    }

    private function sessionIdIsValid(string $sessionId): bool
    {
        return preg_match('/\Acs_[A-Za-z0-9_]+\z/', $sessionId) === 1
            && strlen($sessionId) <= 255;
    }

    private function sessionIdForLog(string $sessionId): ?string
    {
        if ($sessionId === '' || strlen($sessionId) > 255) {
            return null;
        }

        return $sessionId;
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    private function paymentPayload(array $session): array
    {
        $paymentIntent = $session['payment_intent'] ?? null;

        return array_filter([
            'checkout_session_id' => is_string($session['id'] ?? null) ? $session['id'] : null,
            'payment_intent' => is_string($paymentIntent) ? $paymentIntent : null,
            'fulfilled_from' => 'checkout_success_url',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $session
     */
    private function logSkip(Order $order, ?string $sessionId, string $reason, ?array $session = null): void
    {
        Log::info('Stripe checkout return skipped', array_filter([
            'order_id' => $order->id,
            'session_id' => $sessionId,
            'payment_status' => is_string($session['payment_status'] ?? null) ? $session['payment_status'] : null,
            'reason' => $reason,
        ], fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    /**
     * @return array{order: Order, outcome: StripeCheckoutFulfillmentOutcome}
     */
    private function result(Order $order, StripeCheckoutFulfillmentOutcome $outcome): array
    {
        return [
            'order' => $order,
            'outcome' => $outcome,
        ];
    }
}
