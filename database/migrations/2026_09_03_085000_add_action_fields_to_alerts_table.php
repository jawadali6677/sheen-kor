<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->unsignedBigInteger('action_user_id')->nullable();
            $table->timestamp('action_taken_at')->nullable();
            $table->timestamp('fixed_at')->nullable();
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('alerts', function (Blueprint $table) {
                $table->foreign('action_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE alerts MODIFY COLUMN status ENUM('open', 'acknowledged', 'resolved', 'in_progress', 'fixed') NOT NULL DEFAULT 'open'");
        }

        DB::table('alerts')->where('status', 'acknowledged')->update(['status' => 'in_progress']);
        DB::table('alerts')->where('status', 'resolved')->update(['status' => 'fixed']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE alerts MODIFY COLUMN status ENUM('open', 'in_progress', 'fixed') NOT NULL DEFAULT 'open'");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE alerts MODIFY COLUMN status ENUM('open', 'acknowledged', 'resolved', 'in_progress', 'fixed') NOT NULL DEFAULT 'open'");
        }

        DB::table('alerts')->where('status', 'in_progress')->update(['status' => 'acknowledged']);
        DB::table('alerts')->where('status', 'fixed')->update(['status' => 'resolved']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE alerts MODIFY COLUMN status ENUM('open', 'acknowledged', 'resolved') NOT NULL DEFAULT 'open'");
        }

        Schema::table('alerts', function (Blueprint $table) {
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['action_user_id']);
            }

            $table->dropColumn(['action_user_id', 'action_taken_at', 'fixed_at']);
        });
    }
};
