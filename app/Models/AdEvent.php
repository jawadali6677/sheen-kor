<?php

namespace App\Models;

use App\Enums\AdEventType;
use App\Enums\AdPlacement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdEvent extends Model
{
    protected $fillable = [
        'advertisement_id',
        'user_id',
        'visitor_key',
        'type',
        'placement',
    ];

    protected function casts(): array
    {
        return [
            'type' => AdEventType::class,
            'placement' => AdPlacement::class,
        ];
    }

    public function advertisement(): BelongsTo
    {
        return $this->belongsTo(Advertisement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
