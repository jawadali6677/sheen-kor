<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advertisements', function (Blueprint $table) {
            $table->boolean('is_video')->default(false)->after('is_sidebar');
            $table->boolean('is_rewarded')->default(false)->after('is_video');
        });

        $now = now();

        DB::table('advertisements')->insert([
            [
                'slug' => 'demo-video-interstitial',
                'advertiser' => 'Watershed Works',
                'title' => 'Keep storm drains clear this week',
                'description' => 'A short community reminder between videos. Placeholder creative until a video ad provider is connected.',
                'cta' => 'Learn more',
                'image_url' => 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?auto=format&fit=crop&w=800&q=80',
                'destination_url' => 'https://example.com/watershed',
                'is_feed' => false,
                'is_sidebar' => false,
                'is_video' => true,
                'is_rewarded' => false,
                'is_enabled' => true,
                'sort_order' => 30,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'demo-rewarded',
                'advertiser' => 'Sheen Kor Sponsors',
                'title' => 'Optional sponsored video',
                'description' => 'Placeholder rewarded creative. Completion is not verified until an ad provider is connected.',
                'cta' => 'Watch later',
                'image_url' => 'https://images.unsplash.com/photo-1501004318641-b39e6451bec6?auto=format&fit=crop&w=800&q=80',
                'destination_url' => 'https://example.com/rewarded',
                'is_feed' => false,
                'is_sidebar' => false,
                'is_video' => false,
                'is_rewarded' => true,
                'is_enabled' => true,
                'sort_order' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('advertisements')->whereIn('slug', ['demo-video-interstitial', 'demo-rewarded'])->delete();

        Schema::table('advertisements', function (Blueprint $table) {
            $table->dropColumn(['is_video', 'is_rewarded']);
        });
    }
};
