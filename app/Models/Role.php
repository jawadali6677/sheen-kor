<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Role extends Model
{
    public const ADMIN = 'admin';

    public const USER = 'user';

    public const MODERATOR = 'moderator';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Role $role): void {
            if (! filled($role->slug)) {
                $role->slug = Str::slug($role->name);
            }
        });
    }

    public function permissionRecords(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'slug');
    }

    /**
     * @return list<string>
     */
    public function permissionValues(): array
    {
        if ($this->isAdmin()) {
            return array_map(
                fn (Permission $permission): string => $permission->value,
                Permission::cases(),
            );
        }

        return $this->permissionRecords()
            ->pluck('permission')
            ->all();
    }

    public function hasPermission(Permission $permission): bool
    {
        return in_array($permission->value, $this->permissionValues(), true);
    }

    public function isAdmin(): bool
    {
        return $this->slug === self::ADMIN;
    }

    public function syncPermissions(array $permissions): void
    {
        $allowed = array_values(array_intersect(
            $permissions,
            array_map(fn (Permission $permission): string => $permission->value, Permission::cases()),
        ));

        $this->permissionRecords()->delete();

        foreach ($allowed as $permission) {
            $this->permissionRecords()->create([
                'permission' => $permission,
            ]);
        }
    }
}
