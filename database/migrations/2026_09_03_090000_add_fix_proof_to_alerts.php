<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->string('fixed_location_name')->nullable();
            $table->decimal('fixed_latitude', 10, 7)->nullable();
            $table->decimal('fixed_longitude', 10, 7)->nullable();
        });

        Schema::table('alert_images', function (Blueprint $table) {
            $table->string('kind')->default('report');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropColumn([
                'fixed_location_name',
                'fixed_latitude',
                'fixed_longitude',
            ]);
        });

        Schema::table('alert_images', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
