<?php

namespace App\Models;

use App\Enums\ConversationParticipantRole;
use App\Enums\ConversationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title',
        'pair_key',
        'created_by',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConversationType::class,
            'last_message_at' => 'datetime',
        ];
    }

    public static function pairKey(int $firstUserId, int $secondUserId): string
    {
        $ids = [$firstUserId, $secondUserId];
        sort($ids);

        return $ids[0].':'.$ids[1];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'last_read_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function isDirect(): bool
    {
        return $this->type === ConversationType::Direct;
    }

    public function isGroup(): bool
    {
        return $this->type === ConversationType::Group;
    }

    public function hasParticipant(User $user): bool
    {
        if ($this->relationLoaded('participants')) {
            return $this->participants->contains('id', $user->id);
        }

        return $this->participants()->where('users.id', $user->id)->exists();
    }

    public function participantRole(User $user): ?ConversationParticipantRole
    {
        $participant = $this->relationLoaded('participants')
            ? $this->participants->firstWhere('id', $user->id)
            : $this->participants()->where('users.id', $user->id)->first();

        if ($participant === null) {
            return null;
        }

        return ConversationParticipantRole::from($participant->pivot->role);
    }

    public function isAdmin(User $user): bool
    {
        return $this->participantRole($user) === ConversationParticipantRole::Admin;
    }

    public function displayTitle(User $viewer): string
    {
        if ($this->isGroup()) {
            return (string) $this->title;
        }

        $other = $this->relationLoaded('participants')
            ? $this->participants->firstWhere('id', '!=', $viewer->id)
            : $this->participants()->where('users.id', '!=', $viewer->id)->first();

        return $other?->name ?? 'Direct message';
    }

    public function unreadCountFor(User $user): int
    {
        $lastReadAt = $this->relationLoaded('participants')
            ? $this->participants->firstWhere('id', $user->id)?->pivot->last_read_at
            : $this->participants()->where('users.id', $user->id)->first()?->pivot->last_read_at;

        return $this->messages()
            ->where('user_id', '!=', $user->id)
            ->when($lastReadAt, fn ($query) => $query->where('created_at', '>', $lastReadAt))
            ->count();
    }
}
