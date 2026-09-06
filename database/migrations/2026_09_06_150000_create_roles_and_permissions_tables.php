<?php

use App\Enums\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL DEFAULT 'user'");
        }

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 50)->unique();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->timestamps();

            $table->unique(['role_id', 'permission']);
        });

        Schema::create('user_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->timestamps();

            $table->unique(['user_id', 'permission']);
        });

        $now = now();

        $member = [
            Permission::CreatePosts->value,
            Permission::CreateAlerts->value,
            Permission::TakeActionOnAlerts->value,
            Permission::MarkAlertsFixed->value,
        ];

        $moderator = [
            ...$member,
            Permission::ModeratePosts->value,
            Permission::ModerateAlerts->value,
            Permission::ModerateComments->value,
            Permission::ViewAnalytics->value,
        ];

        $this->seedRole('User', 'user', 'Default member role.', true, $member, $now);
        $this->seedRole('Moderator', 'moderator', 'Can moderate community content.', true, $moderator, $now);
        $this->seedRole(
            'Admin',
            'admin',
            'Full access. Always has every permission.',
            true,
            array_map(fn (Permission $permission): string => $permission->value, Permission::cases()),
            $now
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }

    /**
     * @param  list<string>  $permissions
     */
    private function seedRole(string $name, string $slug, string $description, bool $isSystem, array $permissions, mixed $now): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'is_system' => $isSystem,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($permissions as $permission) {
            DB::table('role_permissions')->insert([
                'role_id' => $roleId,
                'permission' => $permission,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
