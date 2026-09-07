<?php

namespace App\Models;

use App\Models\Concerns\HasMediaFile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostImage extends Model
{
    use HasFactory, HasMediaFile;

    protected $fillable = [
        'post_id',
        'image',
        'caption',
        'sort_order',
        'media_type',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'media_type' => 'image',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
