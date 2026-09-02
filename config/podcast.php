<?php

$default_site_url = 'https://bomoeller.dk/';
$site_url = rtrim(getenv('BOMOELLERSHOW_SITE_URL') ?: $default_site_url, '/') . '/';

return [
    'site_name' => 'Bo Møller showet',
    'site_url' => $site_url,
    'short_url' => rtrim(getenv('BOMOELLERSHOW_SHORT_URL') ?: $site_url, '/'),
    'rss_url' => getenv('BOMOELLERSHOW_RSS_URL') ?: 'https://anchor.fm/s/11693a578/podcast/rss',
    'public_rss_url' => 'https://anchor.fm/s/11693a578/podcast/rss',
    'youtube_channel_id' => 'UCmXxpMfYf5gQLQ6GHNeWD3g',
    'youtube_feed_url' => getenv('BOMOELLERSHOW_YOUTUBE_FEED_URL')
        ?: 'https://www.youtube.com/feeds/videos.xml?channel_id=UCmXxpMfYf5gQLQ6GHNeWD3g',
    'series_description' => 'I min personlige podcast deler jeg tanker om iværksætteri, business, software, livet og alt det midt imellem - optaget på farten.',
    'episode_image_overrides' => [],
    'hosts' => [
        [
            'name' => 'Bo Møller',
            'slug' => 'bo-moeller',
            'url' => 'https://bandeja.org',
            'linkedin' => 'https://www.linkedin.com/in/moelleren/',
            'image' => '/assets/hosts/bo-moeller.webp',
            'image_width' => 1196,
            'image_height' => 1200,
            'role' => 'Jeg bygger virksomheder, investerer og fortæller',
            'job_title' => 'Vært, serieiværksætter og forretningsmand',
            'bio' => 'Jeg er dansk serieiværksætter med en forkærlighed for software, business og nye idéer.',
            'long_bio' => 'Jeg er dansk serieiværksætter og forretningsmand. I Bo Møller showet deler jeg de tanker, der opstår på lange køreture, gåture og andre steder, hvor jeg får plads til at tænke frit - om iværksætteri, software, business og livet.',
            'newsletter' => 'https://confirmsubscription.com/h/t/6839F4FAFC2AB8F0',
            'knowsAbout' => ['iværksætteri', 'software', 'business', 'automatisering', 'opkøb', 'produktudvikling'],
            'companies' => [
                ['name' => 'Alunta', 'url' => 'https://alunta.com'],
                ['name' => 'resOS', 'url' => 'https://resos.com'],
                ['name' => 'AnyHOA', 'url' => 'https://anyhoa.com'],
                ['name' => 'PingPuffin', 'url' => 'https://pingpuffin.com'],
            ],
            'sameAs' => [
                'https://bandeja.org',
                'https://www.linkedin.com/in/moelleren/',
                'https://www.youtube.com/@bomoellerdk',
            ],
        ],
    ],
    'platforms' => [
        'Spotify' => 'https://open.spotify.com/show/6OY3tjsfBrOHIvqiPuKif7',
        'Apple Podcasts' => 'https://podcasts.apple.com/dk/podcast/bo-m%C3%B8ller-showet/id6806283860',
        'YouTube' => 'https://www.youtube.com/@bomoellerdk',
    ],
];
