<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisements', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('advertiser');
            $table->string('title');
            $table->text('description');
            $table->string('cta');
            $table->string('image_url')->nullable();
            $table->string('destination_url');
            $table->boolean('is_feed')->default(true);
            $table->boolean('is_sidebar')->default(true);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('advertisements')->insert([
            [
                'slug' => 'demo-bottles',
                'advertiser' => 'GreenPath Supply',
                'title' => 'Refill bottles for every trail',
                'description' => 'Durable bottles made for daily use. A small swap that keeps plastic out of parks.',
                'cta' => 'Learn more',
                'image_url' => 'https://images.unsplash.com/photo-1523362628745-0c100150b504?auto=format&fit=crop&w=800&q=80',
                'destination_url' => 'https://example.com/greenpath',
                'is_feed' => true,
                'is_sidebar' => true,
                'is_enabled' => true,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'demo-trees',
                'advertiser' => 'Canopy Collective',
                'title' => 'Plant a tree with your next walk',
                'description' => 'Local planting days across the city. Bring gloves, leave with a greener street.',
                'cta' => 'See events',
                'image_url' => 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?auto=format&fit=crop&w=800&q=80',
                'destination_url' => 'https://example.com/canopy',
                'is_feed' => true,
                'is_sidebar' => true,
                'is_enabled' => true,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisements');
    }
};
