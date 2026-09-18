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

        return (object) [
            'id' => $this->nextSessionId,
            'url' => $this->nextUrl,
        ];
    }

    public function expireSession(string $sessionId): void
    {
        $this->expiredSessions[] = $sessionId;
    }
}
