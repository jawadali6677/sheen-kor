<?php

namespace App\Models;

use App\Enums\RewardedAdStatus;
use App\Enums\RewardType;
use Database\Factories\RewardedAdSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardedAdSession extends Model
{
    /** @use HasFactory<RewardedAdSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'advertisement_id',
        'status',
        'reward_type',
        'reward_value',
        'completion_token',
        'provider_reference',
        'started_at',
        'completed_at',
        'failed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => RewardedAdStatus::class,
            'reward_type' => RewardType::class,
            'reward_value' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function advertisement(): BelongsTo
    {
        return $this->belongsTo(Advertisement::class);
    }
}
