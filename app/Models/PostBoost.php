<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PostBoostSource;
use App\Enums\PostBoostStatus;
use Database\Factories\PostBoostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostBoost extends Model
{
    /** @use HasFactory<PostBoostFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'post_id',
        'package_id',
        'order_id',
        'status',
        'source',
        'package_type',
        'package_name',
        'package_slug',
        'duration_days',
        'price',
        'currency',
        'starts_at',
        'ends_at',
        'activated_by',
        'activated_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => PostBoostStatus::class,
            'source' => PostBoostSource::class,
            'duration_days' => 'integer',
            'price' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(MonetizationPackage::class, 'package_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /**
     * @param  Builder<PostBoost>  $query
     */
    public function scopeCurrentlyActive(Builder $query): void
    {
        $query->where('status', PostBoostStatus::Active)
            ->where('ends_at', '>', now());
    }

    public function isCurrentlyActive(): bool
    {
        return $this->status === PostBoostStatus::Active
            && $this->ends_at !== null
            && $this->ends_at->isFuture();
    }

    public function displayStatus(): PostBoostStatus
    {
        if ($this->status === PostBoostStatus::Active && $this->ends_at?->isPast()) {
            return PostBoostStatus::Expired;
        }

        return $this->status;
    }

    public function activeUntilPhrase(): ?string
    {
        if (! $this->isCurrentlyActive() || $this->ends_at === null) {
            return null;
        }

        return 'Boosted until '.$this->ends_at->toFormattedDateString();
    }

    public function memberStatusLabel(): string
    {
        if ($this->isCurrentlyActive()) {
            return 'Active';
        }

        if ($this->displayStatus() === PostBoostStatus::Pending) {
            return $this->orderIsPaid() ? 'Pending' : 'Pending payment';
        }

        return $this->displayStatus()->label();
    }

    private function orderIsPaid(): bool
    {
        if ($this->relationLoaded('order')) {
            return $this->order?->status === OrderStatus::Paid;
        }

        if ($this->order_id === null) {
            return false;
        }

        return $this->order()->where('status', OrderStatus::Paid->value)->exists();
    }

    public function activateFromSnapshot(?User $activator = null): void
    {
        $startsAt = now();

        $this->forceFill([
            'status' => PostBoostStatus::Active,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays($this->duration_days),
            'activated_by' => $activator?->id,
            'activated_at' => $startsAt,
        ])->save();
    }
}
