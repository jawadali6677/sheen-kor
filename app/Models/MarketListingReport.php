<?php

namespace App\Models;

use App\Enums\MarketListingReportReason;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketListingReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'market_listing_id',
        'reason',
        'details',
        'status',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'reason' => MarketListingReportReason::class,
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function listing()
    {
        return $this->belongsTo(MarketListing::class, 'market_listing_id');
    }
}
