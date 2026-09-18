<?php

namespace App\Models;

use App\Enums\UserVerificationSource;
use App\Enums\UserVerificationStatus;
use Database\Factories\UserVerificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserVerification extends Model
{
    /** @use HasFactory<UserVerificationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'package_id',
        'order_id',
        'status',
        'source',
        'package_name',
        'package_slug',
        'duration_days',
        'price',
        'currency',
        'starts_at',
        'ends_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => UserVerificationStatus::class,
            'source' => UserVerificationSource::class,
            'duration_days' => 'integer',
            'price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reviewed_at' => 'datetime',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @param  Builder<UserVerification>  $query
     */
    public function scopeCurrentlyActive(Builder $query): void
    {
        $query->where('status', UserVerificationStatus::Active)
            ->where('ends_at', '>', now());
    }

    public function isCurrentlyActive(): bool
    {
        return $this->status === UserVerificationStatus::Active
            && $this->ends_at !== null
            && $this->ends_at->isFuture();
    }

    public function displayStatus(): UserVerificationStatus
    {
        if ($this->status === UserVerificationStatus::Active && $this->ends_at?->isPast()) {
            return UserVerificationStatus::Expired;
        }

        return $this->status;
    }

    public function activateFromSnapshot(): void
    {
        $startsAt = now();

        $this->forceFill([
            'status' => UserVerificationStatus::Active,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays($this->duration_days),
            'reviewed_at' => $startsAt,
        ])->save();
    }
}
