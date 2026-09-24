<?php

namespace Tests\Support;

use App\Contracts\StripeCheckoutGateway;
use App\Models\User;

class FakeStripeCheckoutGateway implements StripeCheckoutGateway
{
    public string $nextSessionId = 'cs_test_123';

    public string $nextUrl = 'https://checkout.stripe.test/cs_test_123';

    public bool $failNext = false;

    /**
     * @var list<array{amount_cents: int, name: string, session_options: array<string, mixed>}>
     */
    public array $charges = [];

    /**
     * @var list<string>
     */
    public array $expiredSessions = [];

    public int $retrieveCount = 0;

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $sessions = [];

    /**
     * @param  array<string, mixed>  $sessionOptions
     */
    public function createOneOffCheckout(User $user, int $amountCents, string $name, array $sessionOptions): ?object
    {
        if ($this->failNext) {
            $this->failNext = false;

            return null;
        }

        $sessionId = $this->nextSessionId;
        $url = $this->nextUrl;

        $this->charges[] = [
            'amount_cents' => $amountCents,
            'name' => $name,
            'session_options' => $sessionOptions,
        ];

        $this->nextSessionId = 'cs_test_'.(123 + count($this->charges));
        $this->nextUrl = 'https://checkout.stripe.test/'.$this->nextSessionId;

        $metadata = is_array($sessionOptions['metadata'] ?? null) ? $sessionOptions['metadata'] : [];
        $orderId = $metadata['order_id'] ?? null;
        $clientReferenceId = $sessionOptions['client_reference_id'] ?? null;

        $this->sessions[$sessionId] = [
            'id' => $sessionId,
            'payment_status' => 'unpaid',
            'order_id' => is_scalar($orderId) && (string) $orderId !== '' ? (string) $orderId : null,
            'client_reference_id' => is_scalar($clientReferenceId) && (string) $clientReferenceId !== '' ? (string) $clientReferenceId : null,
            'payment_intent' => null,
        ];

        return (object) [
            'id' => $sessionId,
            'url' => $url,
        ];
    }

    public function retrieveCheckoutSession(string $sessionId): ?array
    {
        $this->retrieveCount++;

        return $this->sessions[$sessionId] ?? null;
    }

    public function expireSession(string $sessionId): void
    {
        $this->expiredSessions[] = $sessionId;
    }

    public function markSessionPaid(string $sessionId, string $status = 'paid'): void
    {
        if (! isset($this->sessions[$sessionId])) {
            return;
        }

        $this->sessions[$sessionId]['payment_status'] = $status;
    }
}
