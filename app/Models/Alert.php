<?php

namespace App\Models;

use App\Models\Concerns\HasEngagement;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasEngagement, HasFactory;

    protected $fillable = [
        'user_id',
        'action_user_id',
        'title',
        'slug',
        'description',
        'location_name',
        'latitude',
        'longitude',
        'featured_image',
        'severity',
        'status',
        'action_taken_at',
        'fixed_at',
        'fixed_location_name',
        'fixed_latitude',
        'fixed_longitude',
        'views',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'fixed_latitude' => 'float',
            'fixed_longitude' => 'float',
            'action_taken_at' => 'datetime',
            'fixed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actionUser()
    {
        return $this->belongsTo(User::class, 'action_user_id');
    }

    public function images()
    {
        return $this->hasMany(AlertImage::class)->orderBy('sort_order');
    }

    public function reportImages()
    {
        return $this->hasMany(AlertImage::class)
            ->where('kind', 'report')
            ->orderBy('sort_order');
    }

    public function fixImages()
    {
        return $this->hasMany(AlertImage::class)
            ->where('kind', 'fix')
            ->orderBy('sort_order');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isFixed(): bool
    {
        return $this->status === 'fixed';
    }

    public function canBeClaimedBy(?int $userId): bool
    {
        return $userId !== null && $this->isOpen();
    }

    public function canBeFixedBy(?int $userId): bool
    {
        return $userId !== null
            && $this->isInProgress()
            && $this->action_user_id === $userId;
    }
}
