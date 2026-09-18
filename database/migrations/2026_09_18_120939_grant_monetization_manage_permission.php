<?php

use App\Enums\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->grant('admin', [Permission::ManageMonetization->value], now());
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('slug', 'admin')->value('id');

        if ($roleId === null) {
            return;
        }

        DB::table('role_permissions')
            ->where('role_id', $roleId)
            ->where('permission', Permission::ManageMonetization->value)
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
