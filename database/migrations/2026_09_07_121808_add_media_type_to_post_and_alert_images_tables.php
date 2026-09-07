<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_images', function (Blueprint $table) {
            $table->string('media_type', 20)->default('image');
        });

        Schema::table('alert_images', function (Blueprint $table) {
            $table->string('media_type', 20)->default('image');
        });
    }

    public function down(): void
    {
        Schema::table('post_images', function (Blueprint $table) {
            $table->dropColumn('media_type');
        });

        Schema::table('alert_images', function (Blueprint $table) {
            $table->dropColumn('media_type');
        });
    }
};
