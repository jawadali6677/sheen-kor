<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_listings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('market_category_id')
                ->constrained('market_categories')
                ->restrictOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('listing_type');
            $table->string('condition');
            $table->decimal('price', 12, 2)->nullable();
            $table->text('exchange_details')->nullable();
            $table->string('location_name');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('featured_image');
            $table->string('status')->default('pending');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index('listing_type');
            $table->index('condition');
            $table->index(['status', 'latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_listings');
    }
};
