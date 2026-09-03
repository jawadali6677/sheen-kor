<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlertImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'alert_id',
        'image',
        'caption',
        'sort_order',
        'kind',
    ];

    public function alert()
    {
        return $this->belongsTo(Alert::class);
    }
}
