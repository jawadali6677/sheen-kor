<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'user_id',
        'body',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array{
     *     id: int,
     *     conversation_id: int,
     *     body: string,
     *     created_at: string|null,
     *     created_at_human: string|null,
     *     user: array{id: int|null, name: string, avatar_url: string|null}
     * }
     */
    public function toChatPayload(): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans(),
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name ?? 'Unknown User',
                'avatar_url' => $this->user?->avatarUrl(),
            ],
        ];
    }
}
