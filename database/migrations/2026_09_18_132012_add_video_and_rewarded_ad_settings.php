<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $settings = [
            'video_interstitial_min_seconds' => '15',
            'video_interstitial_cooldown_seconds' => '300',
            'video_interstitial_session_cap' => '2',
            'video_interstitial_daily_cap' => '6',
            'rewarded_ads_enabled' => '0',
            'rewarded_type' => 'profile_visibility_credit',
            'rewarded_value' => '1',
        ];

        foreach ($settings as $key => $value) {
            DB::table('monetization_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        DB::table('monetization_settings')
            ->whereIn('key', [
                'video_interstitial_min_seconds',
                'video_interstitial_cooldown_seconds',
                'video_interstitial_session_cap',
                'video_interstitial_daily_cap',
                'rewarded_ads_enabled',
                'rewarded_type',
                'rewarded_value',
            ])
            ->delete();
    }
};
