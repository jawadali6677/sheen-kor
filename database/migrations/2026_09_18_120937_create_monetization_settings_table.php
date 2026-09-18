<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monetization_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->timestamps();
        });

        $now = now();

        $settings = [
            'currency' => 'USD',
            'green_tick_requires_review' => '1',
            'admin_unpaid_grants_enabled' => '1',
            'eligibility_min_followers' => '500',
            'eligibility_min_published_posts' => '10',
            'eligibility_min_qualified_views_30d' => '5000',
            'feed_ads_posts_enabled' => '1',
            'feed_ads_alerts_enabled' => '1',
            'feed_ad_interval' => '8',
            'feed_ad_jitter' => '1',
            'feed_ad_session_cap' => '6',
            'feed_ad_daily_cap' => '20',
            'feed_ad_cooldown_seconds' => '60',
            'sidebar_ads_enabled' => '1',
            'video_ads_enabled' => '0',
            'boost_max_days' => '30',
            'rewarded_daily_limit' => '1',
            'rewarded_cooldown_minutes' => '1440',
        ];

        foreach ($settings as $key => $value) {
            DB::table('monetization_settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('monetization_settings');
    }
};
