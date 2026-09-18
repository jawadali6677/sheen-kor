<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('monetization_packages')->nullOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('market_listing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->json('snapshot');
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
