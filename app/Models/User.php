<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_image',
        'bio',
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
        'role' => 'user',
        'status' => true,
        'score' => 0,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
            'role' => Role::class,
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

    public function hasRole(Role $role): bool
    {
        return $this->role === $role;
    }

    public function hasPermission(Permission $permission): bool
    {
        return $this->role->has($permission);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin);
    }

    public function isModerator(): bool
    {
        return $this->hasRole(Role::Moderator);
    }
}
