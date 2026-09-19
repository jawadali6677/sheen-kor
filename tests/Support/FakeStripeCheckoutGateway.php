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

    /**
     * @var array<string, object{id: string, url: ?string, payment_status: string, metadata: array<string, mixed>, client_reference_id: ?string, payment_intent: ?string}>
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

        $this->charges[] = [
            'amount_cents' => $amountCents,
            'name' => $name,
            'session_options' => $sessionOptions,
        ];

        $session = (object) [
            'id' => $this->nextSessionId,
            'url' => $this->nextUrl,
            'payment_status' => 'unpaid',
            'metadata' => is_array($sessionOptions['metadata'] ?? null) ? $sessionOptions['metadata'] : [],
            'client_reference_id' => is_string($sessionOptions['client_reference_id'] ?? null)
                ? $sessionOptions['client_reference_id']
                : null,
            'payment_intent' => null,
        ];

        $this->sessions[$session->id] = $session;

        return $session;
    }

    public function expireSession(string $sessionId): void
    {
        $this->expiredSessions[] = $sessionId;
    }

    public function retrieveCheckoutSession(string $sessionId): ?object
    {
        return $this->sessions[$sessionId] ?? null;
    }

    public function markSessionPaid(string $sessionId, string $status = 'paid'): void
    {
        if (! isset($this->sessions[$sessionId])) {
            return;
        }

        $this->sessions[$sessionId]->payment_status = $status;
    }
}
