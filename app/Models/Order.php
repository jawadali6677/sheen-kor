<?php

namespace App\Models;

use App\Contracts\StripeCheckoutGateway;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'package_id',
        'post_id',
        'market_listing_id',
        'status',
        'amount',
        'currency',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'amount' => 'decimal:2',
            'snapshot' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(MonetizationPackage::class, 'package_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketListing::class, 'market_listing_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function verification(): HasOne
    {
        return $this->hasOne(UserVerification::class);
    }

    public function boost(): HasOne
    {
        return $this->hasOne(PostBoost::class);
    }

    public function listingPromotion(): HasOne
    {
        return $this->hasOne(ListingPromotion::class);
    }

    public function amountInCents(): int
    {
        return (int) round(((float) $this->amount) * 100);
    }

    public function shouldChargeWithStripe(): bool
    {
        return stripe_checkout_is_configured()
            && $this->status === OrderStatus::Pending
            && $this->amountInCents() > 0;
    }

    public function expireStripeCheckoutSessions(): void
    {
        $this->payments()
            ->where('provider', PaymentProvider::Stripe)
            ->where('status', PaymentStatus::Pending)
            ->get()
            ->each(function (Payment $payment): void {
                $sessionId = $payment->provider_reference;

                if (! is_string($sessionId) || ! str_starts_with($sessionId, 'cs_')) {
                    return;
                }

                app(StripeCheckoutGateway::class)->expireSession($sessionId);
            });
    }

    public function cancelIfPending(): bool
    {
        if ($this->status !== OrderStatus::Pending) {
            return false;
        }

        $this->expireStripeCheckoutSessions();

        $this->payments()
            ->where('status', PaymentStatus::Pending)
            ->update([
                'status' => PaymentStatus::Cancelled->value,
            ]);

        $this->forceFill([
            'status' => OrderStatus::Cancelled,
        ])->save();

        return true;
    }
}
