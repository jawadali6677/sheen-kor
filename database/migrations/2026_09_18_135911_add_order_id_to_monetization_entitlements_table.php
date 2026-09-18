<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_verifications', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('package_id')->constrained()->nullOnDelete();
        });

        Schema::table('post_boosts', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('package_id')->constrained()->nullOnDelete();
        });

        Schema::table('listing_promotions', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('package_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_verifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });

        Schema::table('post_boosts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });

        Schema::table('listing_promotions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
