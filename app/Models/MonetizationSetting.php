<?php

namespace App\Models;

use Database\Factories\MonetizationSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonetizationSetting extends Model
{
    /** @use HasFactory<MonetizationSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
    ];
}
