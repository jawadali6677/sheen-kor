<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'score')) {
                $table->unsignedInteger('score')->default(0);
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('user', 'moderator', 'admin') NOT NULL DEFAULT 'user'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('user', 'admin') NOT NULL DEFAULT 'user'");
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'score')) {
                $table->dropColumn('score');
            }
        });
    }
};
