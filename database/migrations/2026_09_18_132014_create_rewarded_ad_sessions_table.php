<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rewarded_ad_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('advertisement_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('reward_type');
            $table->unsignedInteger('reward_value');
            $table->uuid('completion_token')->unique();
            $table->string('provider_reference')->nullable()->unique();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewarded_ad_sessions');
    }
};
