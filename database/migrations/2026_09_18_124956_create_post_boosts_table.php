<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_boosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('monetization_packages')->nullOnDelete();
            $table->string('status');
            $table->string('source');
            $table->string('package_type');
            $table->string('package_name');
            $table->string('package_slug');
            $table->unsignedInteger('duration_days');
            $table->decimal('price', 12, 2);
            $table->string('currency', 3);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'status']);
            $table->index(['status', 'ends_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_boosts');
    }
};
