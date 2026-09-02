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
        'title',
        'slug',
        'description',
        'location_name',
        'latitude',
        'longitude',
        'featured_image',
        'severity',
        'status',
        'views',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function images()
    {
        return $this->hasMany(AlertImage::class)->orderBy('sort_order');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
