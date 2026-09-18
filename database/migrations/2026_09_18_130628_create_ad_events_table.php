<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertisement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_key');
            $table->string('type');
            $table->string('placement');
            $table->timestamps();

            $table->index(['visitor_key', 'type', 'created_at']);
            $table->index(['user_id', 'type', 'created_at']);
            $table->index(['advertisement_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_events');
    }
};
