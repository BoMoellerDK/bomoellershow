<?php
const BASE_URL = 'http://127.0.0.1:8877';

$checks = 0;
$failures = [];
$snapshot = @simplexml_load_file(dirname(__DIR__) . '/data/podcast-rss-fallback.xml');
$seed_payload = json_decode((string)@file_get_contents(dirname(__DIR__) . '/data/youtube-catalog-seed.json'), true);
$seed_episodes = is_array($seed_payload) ? ($seed_payload['episodes'] ?? []) : [];
$expected_episode_count = $snapshot ? count($snapshot->channel->item) : 0;
$expected_episodes = [];

if ($snapshot) {
    foreach ($snapshot->channel->item as $item) {
        $itunes = $item->children('itunes', true);
        $number = (int)$itunes->episode;
        $expected_episodes[$number] = [
            'title' => (string)$item->title . ' - Bo Møller showet',
            'image' => 'https://bomoeller.dk/assets/social/episode-' . $number . '.jpg',
        ];
    }
}

function check($condition, $message) {
    global $checks, $failures;
    $checks++;
    if (!$condition) $failures[] = $message;
}

function request_path($path) {
    $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10, 'follow_location' => 0]]);
    $body = @file_get_contents(BASE_URL . $path, false, $context);
    $headers = $http_response_header ?? [];
    $status = 0;
    if (isset($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $match)) $status = (int)$match[1];
    return ['status' => $status, 'headers' => $headers, 'body' => $body === false ? '' : $body];
}

function meta_property($html, $property) {
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//meta[@property="' . $property . '"]/@content');
    return $nodes->length ? $nodes->item(0)->nodeValue : '';
}

$runtime_file = getenv('BOMOELLERSHOW_YOUTUBE_RUNTIME_FILE');
check((bool)$runtime_file, 'Testserveren mangler en isoleret runtime-fil');
if ($runtime_file) {
    file_put_contents($runtime_file, json_encode([
        'channel_id' => 'UCmXxpMfYf5gQLQ6GHNeWD3g',
        'episodes' => ['1' => ['id' => 'TAlehw-gM-s', 'title' => 'Forkert runtime-værdi', 'thumbnail' => 'https://i.ytimg.com/vi/TAlehw-gM-s/maxresdefault.jpg']],
    ]));
}

$home = request_path('/');
check($home['status'] === 200, 'Forsiden svarer ikke 200');
check($expected_episode_count === 4, 'RSS-snapshot indeholder ikke de fire første episoder');
check(substr_count($home['body'], 'class="episode-card-v2"') === 4, 'Forsiden indeholder ikke alle episoder');
check(strpos($home['body'], 'Bo Møller showet') !== false, 'Podcastnavnet mangler');
check(strpos($home['body'], 'Her deler jeg ufiltrerede tanker') !== false, 'Heroen er ikke skrevet i jeg-form');
check(strpos($home['body'], 'Få mine noter') !== false, 'Nyhedsbrevsteksten er ikke skrevet i jeg-form');
check(strpos($home['body'], 'Bo Møller er dansk') === false && strpos($home['body'], 'Få Bos noter') === false, 'Tredjepersonstekst lækker på forsiden');
check(strpos($home['body'], 'Jeg bruger AI. Helt åbent.') !== false && strpos($home['body'], 'human_in_the_loop = true') !== false, 'AI-transparenssektionen mangler');
check(strpos($home['body'], "\u{2014}") === false && stripos($home['body'], '&' . 'mdash;') === false, 'Forsiden indeholder stadig en em-dash');
check(strpos($home['body'], '/assets/hosts/bo-moeller.webp') !== false, 'Det nye Bo-portræt mangler');
check(strpos($home['body'], '/assets/icons/avatar-96.webp') !== false && strpos($home['body'], '/favicon.ico') !== false, 'YouTube-profilbilledet bruges ikke som logo og favicon');
check(meta_property($home['body'], 'og:image') === 'https://bomoeller.dk/assets/social/home.jpg', 'Forsiden bruger ikke sit dedikerede social card');
check(strpos($home['body'], 'Ærlige samtaler om at bygge, drive og sælge softwarevirksomheder') === false, 'Gammel podcastbranding lækker i forsiden');
check(strpos($home['body'], 'https://saaskøbmænd.dk') !== false, 'Link til SaaS Købmænd mangler i Bo-biografien');
check(strpos($home['body'], 'https://langsom.com') !== false, 'Link til Langsom mangler i Bo-biografien');
check(strpos($home['body'], 'https://bandeja.org') !== false && strpos($home['body'], 'https://wmo.dk') !== false, 'Links til Bos websites mangler');
check(strpos($home['body'], '/vi/EsDzRsIDTBU/') !== false, 'Seneste YouTube-thumbnail mangler i episodelisten');
check(strpos($home['body'], 'andre.Der') === false && strpos($home['body'], 'produkt.Det') === false, 'RSS-afsnit bliver samlet uden mellemrum i episodeuddrag');
check(strpos($home['body'], '</html>') !== false, 'HTML-outputtet er afkortet');

$episode_four = request_path('/episode/4-skal-man-have-en-co-founder');
check($episode_four['status'] === 200, 'Episode 4 svarer ikke 200');
check(meta_property($episode_four['body'], 'og:title') === 'Skal man have en co-founder? - Bo Møller showet', 'Episode 4 har forkert OG-titel');
check(meta_property($episode_four['body'], 'og:image') === 'https://bomoeller.dk/assets/social/episode-4.jpg', 'Episode 4 har forkert OG-image');
check(meta_property($episode_four['body'], 'og:image:width') === '1200', 'Episode 4 har forkert OG-billedbredde');
check(meta_property($episode_four['body'], 'og:image:height') === '630', 'Episode 4 har forkert OG-billedhøjde');
check(strpos($episode_four['body'], 'VideoObject') !== false, 'Episode 4 mangler VideoObject-schema');
check(strpos($episode_four['body'], 'AudioObject') !== false, 'Episode 4 mangler AudioObject-schema');
check(strpos($episode_four['body'], 'class="video-embed"') !== false && strpos($episode_four['body'], 'loading="lazy"') !== false, 'Episodevideoen er ikke crawlbar og lazy-loaded');
check(strpos($episode_four['body'], 'preload="metadata"') !== false, 'Lydafspilleren henter ikke metadata');
check(strpos($episode_four['body'], 'Det får jeg talt om') !== false && strpos($episode_four['body'], 'Kapitler') !== false, 'Det redaktionelle episodeindhold mangler');
check(strpos($episode_four['body'], 'onclick=') === false, 'Inline JavaScript forhindrer en stram CSP');
check(strpos($episode_four['body'], 'class="hosts-bio"') === false && strpos($episode_four['body'], 'class="subpage-note"') !== false, 'Episodesiden bruger ikke det kompakte afslutningsmodul');

$host = request_path('/vaert/bo-moeller');
check($host['status'] === 200 && strpos($host['body'], 'serieiværksætter') !== false, 'Bos værtsside virker ikke');

$short = request_path('/e/4');
check($short['status'] === 301, 'Kortlinket giver ikke 301');
check((bool)array_filter($short['headers'], function ($header) {
    return stripos($header, 'Location: https://bomoeller.dk/episode/4-') === 0;
}), 'Kortlinket mangler korrekt Location-header');

$sitemap = request_path('/sitemap.xml');
check($sitemap['status'] === 200, 'Sitemap svarer ikke 200');
preg_match_all('#<loc>([^<]+)</loc>#', $sitemap['body'], $sitemap_matches);
$sitemap_urls = $sitemap_matches[1];
$episode_urls = array_values(array_filter($sitemap_urls, function ($url) { return strpos($url, '/episode/') !== false; }));
check(count($episode_urls) === 4, 'Sitemap indeholder ikke alle episodesider');
check(count($sitemap_urls) === 6, 'Sitemap mangler forside eller værtsside');
check(strpos($sitemap['body'], 'xmlns:video=') !== false && substr_count($sitemap['body'], '<video:video>') === 4, 'Video-sitemapdata mangler');

$rendered_titles = [];
$rendered_images = [];
foreach ($episode_urls as $episode_url) {
    $path = parse_url($episode_url, PHP_URL_PATH);
    $episode = request_path($path);
    preg_match('#/episode/(\d+)-#', $path, $number_match);
    $number = (int)($number_match[1] ?? 0);
    $expected = $expected_episodes[$number] ?? ['title' => '', 'image' => ''];
    $title = meta_property($episode['body'], 'og:title');
    $image = meta_property($episode['body'], 'og:image');
    check($episode['status'] === 200, $path . ' svarer ikke 200');
    check($title === $expected['title'], $path . ' har forkert OG-titel');
    check($image === $expected['image'], $path . ' har forkert OG-image');
    check(meta_property($episode['body'], 'og:image:type') === 'image/jpeg', $path . ' har forkert OG-billedtype');
    $rendered_titles[] = $title;
    $rendered_images[] = $image;
}
check(count(array_unique($rendered_titles)) === 4, 'Episodesiderne har ikke unikke OG-titler');
check(count(array_unique($rendered_images)) === 4, 'Episodesiderne har ikke unikke OG-images');

if ($runtime_file && is_file($runtime_file)) {
    $payload = json_decode((string)file_get_contents($runtime_file), true);
    check(empty($payload['episodes']), 'Runtime-kataloget indeholder kopier eller overskriver seed-data');
    @unlink($runtime_file);
}

echo json_encode(['checks' => $checks, 'episodes' => $expected_episode_count, 'failures' => $failures], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failures ? 1 : 0);
