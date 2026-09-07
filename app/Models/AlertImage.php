<?php

namespace App\Models;

use App\Models\Concerns\HasMediaFile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlertImage extends Model
{
    use HasFactory, HasMediaFile;

    protected $fillable = [
        'alert_id',
        'image',
        'caption',
        'sort_order',
        'kind',
        'media_type',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'report',
        'media_type' => 'image',
    ];

    public function alert()
    {
        return $this->belongsTo(Alert::class);
    }
}
