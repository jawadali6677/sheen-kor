<?php

namespace App\Models;

use App\Enums\Permission;
use App\Models\Role as AccessRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_image',
        'cover_image',
        'bio',
        'username',
        'location',
        'website',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => AccessRole::USER,
        'status' => true,
        'score' => 0,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
            'score' => 'integer',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function claimedAlerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'action_user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function followings(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')
            ->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')
            ->withTimestamps();
    }

    public function isFollowing(User $user): bool
    {
        if ($this->relationLoaded('followings')) {
            return $this->followings->contains('id', $user->id);
        }

        return $this->followings()->where('users.id', $user->id)->exists();
    }

    public function isFollowedBy(User $user): bool
    {
        if ($this->relationLoaded('followers')) {
            return $this->followers->contains('id', $user->id);
        }

        return $this->followers()->where('users.id', $user->id)->exists();
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class)
            ->withPivot(['role', 'last_read_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function unreadConversationCount(): int
    {
        return $this->conversations()
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('messages')
                    ->whereColumn('messages.conversation_id', 'conversations.id')
                    ->where('messages.user_id', '!=', $this->id)
                    ->where(function ($query): void {
                        $query->whereNull('conversation_user.last_read_at')
                            ->orWhereColumn('messages.created_at', '>', 'conversation_user.last_read_at');
                    });
            })
            ->count();
    }

    public function sentTips(): HasMany
    {
        return $this->hasMany(Tip::class, 'sender_id');
    }

    public function receivedTips(): HasMany
    {
        return $this->hasMany(Tip::class, 'receiver_id');
    }

    public function scoreEvents(): HasMany
    {
        return $this->hasMany(ScoreEvent::class);
    }

    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(AccessRole::class, 'role', 'slug');
    }

    public function extraPermissionRecords(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function roleLabel(): string
    {
        return $this->assignedRole?->name ?? Str::headline((string) $this->role);
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasPermission(Permission $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->assignedRole?->hasPermission($permission)) {
            return true;
        }

        return $this->extraPermissionRecords()
            ->where('permission', $permission->value)
            ->exists();
    }

    /**
     * @return list<Permission>
     */
    public function assignablePermissions(): array
    {
        if ($this->isAdmin()) {
            return Permission::cases();
        }

        return array_values(array_filter(
            Permission::cases(),
            fn (Permission $permission): bool => $this->hasPermission($permission),
        ));
    }

    /**
     * @return list<string>
     */
    public function extraPermissionValues(): array
    {
        return $this->extraPermissionRecords()->pluck('permission')->all();
    }

    /**
     * @param  list<string>  $permissions
     */
    public function syncExtraPermissions(array $permissions): void
    {
        $allowed = array_values(array_intersect(
            $permissions,
            array_map(fn (Permission $permission): string => $permission->value, Permission::cases()),
        ));

        $this->extraPermissionRecords()->delete();

        foreach ($allowed as $permission) {
            $this->extraPermissionRecords()->create([
                'permission' => $permission,
            ]);
        }
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(AccessRole::ADMIN);
    }

    public function isModerator(): bool
    {
        return $this->hasRole(AccessRole::MODERATOR);
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
    }

    public function avatarUrl(): ?string
    {
        if (! $this->profile_image) {
            return null;
        }

        return asset('storage/'.$this->profile_image);
    }

    public function coverUrl(): ?string
    {
        if (! $this->cover_image) {
            return null;
        }

        return asset('storage/'.$this->cover_image);
    }

    public function profileCompletionPercent(): int
    {
        $fields = ['name', 'username', 'bio', 'location', 'website', 'profile_image', 'cover_image'];
        $filled = 0;

        foreach ($fields as $field) {
            if (filled($this->{$field})) {
                $filled++;
            }
        }

        return (int) round(($filled / count($fields)) * 100);
    }
}
