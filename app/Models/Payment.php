<?php

namespace App\Models;

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'provider' => 'manual',
    ];

    protected $fillable = [
        'order_id',
        'provider',
        'provider_reference',
        'idempotency_key',
        'amount',
        'currency',
        'status',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'payload' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public static function checkoutIdempotencyKey(int $orderId, int $attempt = 1): string
    {
        if ($attempt <= 1) {
            return 'order:'.$orderId.':checkout';
        }

        return 'order:'.$orderId.':checkout:'.$attempt;
    }

    public function stripeCheckoutSessionId(): ?string
    {
        $sessionId = $this->provider_reference;

        if (is_string($sessionId) && str_starts_with($sessionId, 'cs_')) {
            return $sessionId;
        }

        $payloadSession = $this->payload['checkout_session_id'] ?? null;

        return is_string($payloadSession) && str_starts_with($payloadSession, 'cs_')
            ? $payloadSession
            : null;
    }
}
