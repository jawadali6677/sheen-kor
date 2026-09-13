<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_listing_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('market_listing_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('reason');
            $table->text('details')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['user_id', 'market_listing_id']);
            $table->index(['market_listing_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_listing_reports');
    }
};
