<?php

namespace App\Models;

use App\Enums\ScoreReason;
use Database\Factories\ScoreEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ScoreEvent extends Model
{
    /** @use HasFactory<ScoreEventFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reason',
        'points',
        'source_type',
        'source_id',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ScoreReason::class,
            'points' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
