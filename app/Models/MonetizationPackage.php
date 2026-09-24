<?php

namespace App\Models;

use App\Enums\MonetizationPackageType;
use Database\Factories\MonetizationPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonetizationPackage extends Model
{
    /** @use HasFactory<MonetizationPackageFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_enabled' => false,
        'sort_order' => 0,
    ];

    protected $fillable = [
        'type',
        'name',
        'slug',
        'duration_days',
        'placement',
        'price',
        'currency',
        'is_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => MonetizationPackageType::class,
            'duration_days' => 'integer',
            'price' => 'decimal:2',
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function promoteCheckoutLabel(): string
    {
        return $this->durationCheckoutLabel('Promote for');
    }

    public function boostCheckoutLabel(): string
    {
        return $this->durationCheckoutLabel('Boost this post for');
    }

    private function durationCheckoutLabel(string $lead): string
    {
        $days = (int) $this->duration_days;
        $dayWord = $days === 1 ? 'day' : 'days';

        return $lead.' '.$days.' '.$dayWord.' · '.$this->name.' · '.$this->price.' '.$this->currency;
    }
}
