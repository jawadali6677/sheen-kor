<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'commentable_id',
        'commentable_type',
        'parent_id',
        'content',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function commentable()
    {
        return $this->morphTo();
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id')
            ->orderBy('created_at');
    }

    public function toEngagementPayload(?User $actor = null): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'content' => $this->content,
            'status' => $this->status,
            'created_at' => $this->created_at?->diffForHumans(),
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name ?? 'Unknown User',
            ],
            'can_edit' => $actor?->can('update', $this) ?? false,
            'can_delete' => $actor?->can('delete', $this) ?? false,
        ];
    }
}
