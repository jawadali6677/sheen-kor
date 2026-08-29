<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'image',
        'caption',
        'sort_order',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}