<?php

use App\Enums\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $this->grant('user', [Permission::CreateMarketListings->value], $now);
        $this->grant('moderator', [
            Permission::CreateMarketListings->value,
            Permission::ModerateMarketListings->value,
        ], $now);
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('slug', ['user', 'moderator'])
            ->pluck('id');

        DB::table('role_permissions')
            ->whereIn('role_id', $roleIds)
            ->whereIn('permission', [
                Permission::CreateMarketListings->value,
                Permission::ModerateMarketListings->value,
            ])
            ->delete();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function grant(string $roleSlug, array $permissions, mixed $now): void
    {
        $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');

        if ($roleId === null) {
            return;
        }

        foreach ($permissions as $permission) {
            $exists = DB::table('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission', $permission)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('role_permissions')->insert([
                'role_id' => $roleId,
                'permission' => $permission,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
