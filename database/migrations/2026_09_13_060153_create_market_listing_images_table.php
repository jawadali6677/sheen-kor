<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_listing_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('market_listing_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('image');
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('media_type', 20)->default('image');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_listing_images');
    }
};
