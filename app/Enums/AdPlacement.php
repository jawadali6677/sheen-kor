<?php

namespace App\Enums;

enum AdPlacement: string
{
    case FeedPosts = 'feed_posts';
    case FeedAlerts = 'feed_alerts';
    case Sidebar = 'sidebar';
    case VideoInterstitial = 'video_interstitial';
    case Rewarded = 'rewarded';

    public function feedEnabledSettingKey(): ?string
    {
        return match ($this) {
            self::FeedPosts => 'feed_ads_posts_enabled',
            self::FeedAlerts => 'feed_ads_alerts_enabled',
            self::Sidebar => 'sidebar_ads_enabled',
            self::VideoInterstitial, self::Rewarded => null,
        };
    }
}
