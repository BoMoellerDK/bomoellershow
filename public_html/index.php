<?php
$config = require dirname(__DIR__) . '/config/podcast.php';
$rss_url = $config['rss_url'];
$public_rss_url = $config['public_rss_url'];
$site_url = $config['site_url'];
$short_url = $config['short_url'];
$youtube_channel_id = $config['youtube_channel_id'];
$youtube_feed_url = $config['youtube_feed_url'];
$episode_image_overrides = $config['episode_image_overrides'];
$site_name = $config['site_name'];
$series_description = $config['series_description'];
$hosts = $config['hosts'];
$platforms = $config['platforms'];
$platform_profiles = array_values($platforms);

// ===== Hjælpefunktioner =====
function dk_slugify($str) {
    $str = mb_strtolower($str, 'UTF-8');
    $repl = ['æ'=>'ae','ø'=>'oe','å'=>'aa','é'=>'e','á'=>'a','ö'=>'o','ü'=>'u','ä'=>'a'];
    $str = strtr($str, $repl);
    $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $str = preg_replace('~[^a-z0-9]+~', '-', $str);
    $str = preg_replace('~-+~', '-', $str);
    $str = trim($str, '-');
    return $str ?: 'episode';
}
function short_title_for_slug($title, $max_words = 6, $max_len = 42) {
    $title = trim(preg_replace('/\s+/', ' ', $title));
    $words = preg_split('/\s+/', $title);
    $words = array_slice($words, 0, $max_words);
    $short = implode(' ', $words);
    if (mb_strlen($short, 'UTF-8') > $max_len) $short = mb_substr($short, 0, $max_len, 'UTF-8');
    return $short;
}
function get_audio_url_from_item($item) {
    if (isset($item->enclosure)) return (string)$item->enclosure['url'];
    if ($item->children('media', true)->content) {
        return (string)$item->children('media', true)->content->attributes()->url;
    }
    return null;
}
function extract_episode_number_from_title($title) {
    if (preg_match('/(?:^|\s)(?:episode|ep|#)\s*(\d+)\b/i', $title, $m)) return (int)$m[1];
    return null;
}
function get_episode_number($item, $fallback) {
    $itunes = $item->children('itunes', true);
    if (isset($itunes->episode) && (int)$itunes->episode > 0) return (int)$itunes->episode;
    $from_title = extract_episode_number_from_title((string)$item->title);
    return $from_title ?: $fallback;
}
function parse_duration_seconds($item) {
    $itunes = $item->children('itunes', true);
    if (!empty($itunes->duration)) {
        $raw = trim((string)$itunes->duration);
        if (ctype_digit($raw)) return (int)$raw;
        $parts = explode(':', $raw);
        if (count($parts) === 3) {
            return ((int)$parts[0])*3600 + ((int)$parts[1])*60 + (int)$parts[2];
        } elseif (count($parts) === 2) {
            return ((int)$parts[0])*60 + (int)$parts[1];
        }
    }
    $media = $item->children('media', true);
    if ($media && $media->content) {
        $dur = $media->content->attributes()->duration ?? null;
        if ($dur !== null && ctype_digit((string)$dur)) return (int)$dur;
    }
    return null;
}
function format_duration($seconds) {
    if ($seconds === null) return null;
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    if ($h > 0) return sprintf('%d:%02d:%02d', $h, $m, $s);
    return sprintf('%d:%02d', $m, $s);
}
function format_views($views) {
    $views = (int)$views;
    if ($views >= 1000000) return rtrim(rtrim(number_format($views / 1000000, 1, ',', '.'), '0'), ',') . ' mio.';
    if ($views >= 1000) return rtrim(rtrim(number_format($views / 1000, 1, ',', '.'), '0'), ',') . 'k';
    return number_format($views, 0, ',', '.');
}
function youtube_thumbnail_srcset($video_id, $primary_thumbnail = '') {
    if (!preg_match('/^[A-Za-z0-9_-]{11}$/', (string)$video_id)) return '';
    $base = 'https://i.ytimg.com/vi/' . rawurlencode($video_id) . '/';
    $candidates = $base . 'mqdefault.jpg 320w, ' . $base . 'hqdefault.jpg 480w';
    if ($primary_thumbnail === '' || strpos($primary_thumbnail, '/sddefault.jpg') !== false || strpos($primary_thumbnail, '/maxresdefault.jpg') !== false) {
        $candidates .= ', ' . $base . 'sddefault.jpg 640w';
    }
    if ($primary_thumbnail === '' || strpos($primary_thumbnail, '/maxresdefault.jpg') !== false) {
        $candidates .= ', ' . $base . 'maxresdefault.jpg 1280w';
    }
    return $candidates;
}
function youtube_thumbnail_dimensions($thumbnail) {
    if (strpos((string)$thumbnail, '/maxresdefault.jpg') !== false) return [1280, 720];
    if (strpos((string)$thumbnail, '/sddefault.jpg') !== false) return [640, 480];
    if (strpos((string)$thumbnail, '/hqdefault.jpg') !== false) return [480, 360];
    if (strpos((string)$thumbnail, '/mqdefault.jpg') !== false) return [320, 180];
    return [1280, 720];
}
function image_mime_type_from_url($url) {
    $path = parse_url((string)$url, PHP_URL_PATH);
    $extension = strtolower(pathinfo((string)$path, PATHINFO_EXTENSION));
    $types = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];
    return $types[$extension] ?? null;
}
function normalize_match_title($title) {
    $title = html_entity_decode((string)$title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $title = mb_strtolower($title, 'UTF-8');
    $title = strtr($title, ['æ'=>'ae','ø'=>'oe','å'=>'aa','é'=>'e','á'=>'a','ö'=>'o','ü'=>'u','ä'=>'a']);
    $title = preg_replace('/\bbo\s+moeller\s+showet\s*(?:(?:episode|ep)\s*)?#?\s*\d+\b/', ' ', $title);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $title));
}
function extract_youtube_episode_number($title) {
    if (preg_match('/(?:#|\bepisode\s+|\bep\.?\s*)(\d+)\b/iu', (string)$title, $m)) return (int)$m[1];
    return null;
}
function youtube_title_similarity($episode_title, $video_title) {
    $a = normalize_match_title($episode_title);
    $b = normalize_match_title($video_title);
    if ($a === '' || $b === '') return 0.0;
    if ($a === $b) return 1.0;

    $stop = ['bo', 'moeller', 'showet', 'episode', 'ep', 'og', 'i', 'af', 'at', 'en', 'et', 'til', 'fra', 'med', 'vi', 'det', 'de', 'den', 'der', 'som', 'man', 'for', 'paa', 'er', 'har', 'om'];
    $tokens_a = array_values(array_diff(array_unique(explode(' ', $a)), $stop));
    $tokens_b = array_values(array_diff(array_unique(explode(' ', $b)), $stop));
    $intersection = count(array_intersect($tokens_a, $tokens_b));
    $union = count(array_unique(array_merge($tokens_a, $tokens_b)));
    $jaccard = $union ? $intersection / $union : 0.0;
    $containment = min(count($tokens_a), count($tokens_b))
        ? $intersection / min(count($tokens_a), count($tokens_b))
        : 0.0;
    similar_text($a, $b, $sequence_percent);
    return 0.35 * $jaccard + 0.35 * $containment + 0.30 * ($sequence_percent / 100);
}
function episode_topics($title, $content) {
    $haystack = mb_strtolower(html_entity_decode(strip_tags($title . ' ' . $content), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'UTF-8');
    $rules = [
        'Teknologi' => '/\b(ai|llm|chatgpt|mcp|software|teknologi|automatisering)\b/u',
        'Business' => '/\b(business|salg|saelg|sælg|kunde|kunder|marketing|firma|forretning)\b/u',
        'Iværksætteri' => '/\b(iværksætter|ivaerksaetter|founder|co-founder|virksomhed|virksomheder)\b/u',
        'Produkt' => '/\b(produkt|bygge|valider|idé|ide|hundemad)\b/u',
        'Livet' => '/\b(livet|mening|filosofi|tanker|langsom|hurtig|alene|andre)\b/u',
    ];
    $topics = [];
    foreach ($rules as $label => $pattern) {
        if (preg_match($pattern, $haystack)) $topics[] = $label;
    }
    return $topics ?: ['Tanker'];
}
// Ren tekst-teaser fra (evt. HTML-)indhold: fjern tags, fold whitespace, klip til længde
function teaser($html, $limit, $ellipsis = true) {
    // Bevar ordgrænser mellem blokke. strip_tags alene ville fx samle
    // "andre.</p><p>Der" til "andre.Der" i kortenes uddrag.
    $html = preg_replace('/<\/?(?:p|div|h[1-6]|li|blockquote|pre|br)\b[^>]*>/iu', ' ', (string)$html);
    $text = strip_tags($html);
    // Afkod entiteter (&quot; &amp; …) til ren tekst – ellers lækker de i llms.txt
    // og dobbelt-encodes (&amp;quot;) når output senere køres gennem htmlspecialchars.
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if (mb_strlen($text, 'UTF-8') <= $limit) return $text;
    return mb_substr($text, 0, $limit, 'UTF-8') . ($ellipsis ? '…' : '');
}
// Podcast-feedet kan indeholde typografiske em-dashes som tegn eller entitet.
// Sitet bruger konsekvent den almindelige bindestreg i al synlig tekst.
function replace_em_dashes($text) {
    return str_ireplace(["\u{2014}", '&' . 'mdash;', '&#' . '8212;', '&#x' . '2014;'], '-', (string)$text);
}
// ISO 8601-varighed (fx "PT1H2M3S") til schema.org timeRequired
function iso8601_duration($seconds) {
    if (!$seconds) return null;
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    $out = 'PT';
    if ($h) $out .= $h . 'H';
    if ($m) $out .= $m . 'M';
    if ($s) $out .= $s . 'S';
    return $out === 'PT' ? 'PT0S' : $out;
}
// Sanitér HTML fra RSS-feedet: tillad kun en whitelist af tags, fjern ALLE
// attributter undtagen sikre href'er på <a> (strip_tags fjerner tags, men ikke
// fx onclick="" eller href="javascript:" på tilladte tags – derfor DOM-rensning).
function sanitize_episode_html($html) {
    $html = (string)$html;
    if (trim($html) === '') return '';

    $allowed = ['p','br','strong','em','b','i','ul','ol','li','a','blockquote','h2','h3','h4','code','pre'];

    // Fallback hvis ext-dom mangler: render som ren tekst (aldrig rå HTML).
    if (!class_exists('DOMDocument')) {
        return nl2br(htmlspecialchars(trim(strip_tags($html)), ENT_QUOTES, 'UTF-8'));
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="UTF-8"><div id="__wrap">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);
    foreach (iterator_to_array($xpath->query('//*')) as $el) {
        if (!$el->parentNode) continue; // allerede fjernet som del af et droppet subtræ
        $tag = strtolower($el->nodeName);
        if ($tag === 'div' && $el->getAttribute('id') === '__wrap') continue; // wrapper

        if (!in_array($tag, $allowed, true)) {
            if (in_array($tag, ['script','style','template','noscript'], true)) {
                // Drop hele subtræet inkl. tekstindhold (ikke bare tagget)
                if ($el->parentNode) $el->parentNode->removeChild($el);
            } else {
                // Andre ikke-tilladte tags: pak indholdet ud (behold teksten)
                while ($el->firstChild) $el->parentNode->insertBefore($el->firstChild, $el);
                $el->parentNode->removeChild($el);
            }
            continue;
        }

        // Fjern alle attributter undtagen en sikker href på <a>
        for ($i = $el->attributes->length - 1; $i >= 0; $i--) {
            $attr = $el->attributes->item($i);
            $keep = $tag === 'a'
                && strtolower($attr->name) === 'href'
                && preg_match('#^(https?:|mailto:|/|\#)#i', trim($attr->value));
            if (!$keep) $el->removeAttribute($attr->name);
        }
        if ($tag === 'a' && $el->getAttribute('href') !== '') {
            $el->setAttribute('rel', 'noopener nofollow ugc');
            $el->setAttribute('target', '_blank');
        }
    }

    $wrap = $xpath->query('//*[@id="__wrap"]')->item(0);
    $out = '';
    if ($wrap) foreach ($wrap->childNodes as $c) $out .= $doc->saveHTML($c);
    return $out;
}
// Fjern tomme felter (null, '', []) rekursivt fra et JSON-LD-træ
function ld_clean($v) {
    if (!is_array($v)) return $v;
    $out = [];
    foreach ($v as $k => $val) {
        $val = ld_clean($val);
        if ($val === null || $val === '' || $val === []) continue;
        $out[$k] = $val;
    }
    return $out;
}

// Skriv en ny fallback atomisk, så et afbrudt write aldrig ødelægger den
// sidst kendte gode version. Fejl ignoreres: nogle webhoteller er read-only.
function save_atomic_file($path, $contents) {
    $directory = dirname($path);
    if (!is_dir($directory) || !is_writable($directory)) return false;
    $temporary = @tempnam($directory, '.rss-');
    if ($temporary === false) return false;
    $written = @file_put_contents($temporary, $contents, LOCK_EX);
    @chmod($temporary, 0644);
    if ($written === false || !@rename($temporary, $path)) {
        @unlink($temporary);
        return false;
    }
    return true;
}

// Hent RSS med to lag cache: hurtig system-cache og en permanent, deployet
// snapshot-fil. Sitet virker derfor også efter genstart, hvis Anchor er nede.
function fetch_rss_cached($rss_url, $ttl = 900, $fallback_file = null) {
    $cache_namespace = (string)getenv('BOMOELLERSHOW_CACHE_NAMESPACE');
    $cache_file = sys_get_temp_dir() . '/bomoellershow_rss_' . md5($rss_url . '|' . $cache_namespace) . '.xml';

    // 1) Frisk cache? Brug den.
    if (is_file($cache_file) && (time() - (int)@filemtime($cache_file) < $ttl)) {
        $xml = @file_get_contents($cache_file);
        if ($xml !== false) {
            $rss = @simplexml_load_string($xml);
            if ($rss) return $rss;
        }
    }

    // 2) Hent friskt feed (med timeout, så en langsom server ikke hænger sitet).
    $ctx = stream_context_create([
        'http' => ['timeout' => 8, 'user_agent' => 'bomoellershow-web/1.0'],
        'https'=> ['timeout' => 8],
    ]);
    $xml = @file_get_contents($rss_url, false, $ctx);
    if ($xml !== false) {
        $rss = @simplexml_load_string($xml);
        if ($rss) {
            @file_put_contents($cache_file, $xml, LOCK_EX);
            if ($fallback_file) save_atomic_file($fallback_file, $xml);
            return $rss;
        }
    }

    // 3) Hentning fejlede – fald tilbage på (evt. forældet) cache.
    if (is_file($cache_file)) {
        $xml = @file_get_contents($cache_file);
        if ($xml !== false) {
            $rss = @simplexml_load_string($xml);
            if ($rss) return $rss;
        }
    }

    // 4) Også system-cachen er væk/defekt: brug den deployede sidste kendte
    // gode version. Det dækker bl.a. et server-restart under Anchor-nedetid.
    if ($fallback_file && is_file($fallback_file)) {
        $xml = @file_get_contents($fallback_file);
        if ($xml !== false) {
            $rss = @simplexml_load_string($xml);
            if ($rss) {
                @file_put_contents($cache_file, $xml, LOCK_EX);
                return $rss;
            }
        }
    }
    return false;
}

// Indlæs permanente episode→video-matches. Seed-filen deployes med sitet;
// runtime-filen opdateres af PHP og overlever servergenstart uden at blive
// overskrevet af senere deployments.
function load_youtube_episode_catalog($catalog_files) {
    $episodes = [];
    foreach ($catalog_files as $catalog_file) {
        if (!$catalog_file || !is_file($catalog_file)) continue;
        $catalog = json_decode((string)@file_get_contents($catalog_file), true);
        if (!is_array($catalog) || !isset($catalog['episodes']) || !is_array($catalog['episodes'])) continue;
        foreach ($catalog['episodes'] as $episode_number => $video) {
            $episode_number = (int)$episode_number;
            if ($episode_number < 1 || !is_array($video) || empty($video['id']) || !preg_match('/^[A-Za-z0-9_-]{11}$/', $video['id'])) continue;
            $video['title'] = (string)($video['title'] ?? '');
            $video['title_key'] = normalize_match_title($video['title']);
            $video['thumbnail'] = (string)($video['thumbnail'] ?? ('https://i.ytimg.com/vi/' . rawurlencode($video['id']) . '/maxresdefault.jpg'));
            $video['date_iso'] = (string)($video['date_iso'] ?? '');
            $video['published_iso'] = (string)($video['published_iso'] ?? '');
            $video['views'] = (int)($video['views'] ?? 0);
            $episodes[$episode_number] = $video;
        }
    }
    return $episodes;
}

function save_youtube_episode_catalog($catalog_file, $channel_id, $episodes) {
    if (!$catalog_file) return false;
    ksort($episodes, SORT_NUMERIC);
    if (is_file($catalog_file)) {
        $existing = json_decode((string)@file_get_contents($catalog_file), true);
        $existing_episodes = is_array($existing) && isset($existing['episodes']) && is_array($existing['episodes'])
            ? $existing['episodes']
            : null;
        if ($existing_episodes !== null) {
            ksort($existing_episodes, SORT_NUMERIC);
            if (($existing['channel_id'] ?? '') === $channel_id && $existing_episodes == $episodes) return true;
        }
    }
    $payload = [
        'channel_id'  => $channel_id,
        'updated_at'  => date('c'),
        'episodes'    => $episodes,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    return $json !== false ? save_atomic_file($catalog_file, $json . "\n") : false;
}

// YouTubes offentlige Atom-feed kræver ingen API-nøgle. Det indeholder de
// seneste uploads; det permanente katalog gør, at en video aldrig glemmes igen.
function fetch_youtube_cached($feed_url, $ttl = 900, $catalog_files = []) {
    $cache_namespace = (string)getenv('BOMOELLERSHOW_CACHE_NAMESPACE');
    $cache_key = md5($feed_url . '|' . $cache_namespace);
    $cache_file = sys_get_temp_dir() . '/bomoellershow_youtube_' . $cache_key . '.xml';
    $history_file = sys_get_temp_dir() . '/bomoellershow_youtube_history_' . $cache_key . '.json';
    $xml = false;
    $videos_by_id = [];

    $episode_catalog = load_youtube_episode_catalog($catalog_files);

    // Migrér også den tidligere temp-historik, hvis den findes.
    if (is_file($history_file)) {
        $stored = json_decode((string)@file_get_contents($history_file), true);
        if (is_array($stored)) {
            foreach ($stored as $video) {
                if (is_array($video) && !empty($video['id']) && preg_match('/^[A-Za-z0-9_-]{11}$/', $video['id'])) {
                    $videos_by_id[$video['id']] = $video;
                }
            }
        }
    }
    // Det permanente katalog er autoritativt over ældre temp-data, bl.a. når
    // en konkret video kræver sddefault frem for en ikke-eksisterende maxres.
    foreach ($episode_catalog as $video) $videos_by_id[$video['id']] = $video;

    if (is_file($cache_file) && (time() - (int)@filemtime($cache_file) < $ttl)) {
        $xml = @file_get_contents($cache_file);
    }
    if ($xml === false) {
        $ctx = stream_context_create([
            'http' => ['timeout' => 8, 'user_agent' => 'bomoellershow-web/1.0'],
            'https'=> ['timeout' => 8],
        ]);
        $fresh = @file_get_contents($feed_url, false, $ctx);
        if ($fresh !== false) {
            $xml = $fresh;
            @file_put_contents($cache_file, $fresh, LOCK_EX);
        } elseif (is_file($cache_file)) {
            $xml = @file_get_contents($cache_file);
        }
    }
    if ($xml === false) return ['videos' => array_values($videos_by_id), 'episodes' => $episode_catalog];

    $feed = @simplexml_load_string($xml);
    if (!$feed) return ['videos' => array_values($videos_by_id), 'episodes' => $episode_catalog];
    foreach ($feed->entry as $entry) {
        $yt = $entry->children('http://www.youtube.com/xml/schemas/2015');
        $media = $entry->children('http://search.yahoo.com/mrss/');
        $video_id = trim((string)$yt->videoId);
        if ($video_id === '') continue;
        $published_ts = strtotime((string)$entry->published);
        $views = 0;
        if (isset($media->group->community->statistics)) {
            $views = (int)$media->group->community->statistics->attributes()->views;
        }
        $feed_thumbnail = '';
        if (isset($media->group->thumbnail)) {
            $thumbnail_attributes = $media->group->thumbnail->attributes();
            $feed_thumbnail = trim((string)($thumbnail_attributes['url'] ?? ''));
        }
        $feed_video = [
            'id' => $video_id,
            'title' => (string)$entry->title,
            'title_key' => normalize_match_title((string)$entry->title),
            'date_iso' => $published_ts ? gmdate('Y-m-d', $published_ts) : '',
            'published_iso' => $published_ts ? gmdate('c', $published_ts) : '',
            // Atom-feedets thumbnail eksisterer med sikkerhed. Højere opløsninger
            // bruges kun, når det kuraterede katalog udtrykkeligt angiver dem.
            'thumbnail' => $feed_thumbnail ?: ('https://i.ytimg.com/vi/' . rawurlencode($video_id) . '/hqdefault.jpg'),
            'views' => $views,
        ];
        foreach ($episode_catalog as $catalog_video) {
            if ($catalog_video['id'] === $video_id && !empty($catalog_video['thumbnail'])) {
                $feed_video['thumbnail'] = $catalog_video['thumbnail'];
                break;
            }
        }
        $videos_by_id[$video_id] = $feed_video;
    }
    $videos = array_values($videos_by_id);
    usort($videos, function ($a, $b) {
        return strcmp((string)($b['published_iso'] ?? ''), (string)($a['published_iso'] ?? ''));
    });
    @file_put_contents(
        $history_file,
        json_encode($videos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );
    return ['videos' => $videos, 'episodes' => $episode_catalog];
}

// Episode thumbnail/cover-art (prioritér stort billede)
function get_episode_image_from_item($item, $fallback = '') {
    $itunes = $item->children('itunes', true);
    if ($itunes && isset($itunes->image)) {
        $attrs = $itunes->image->attributes();
        if ($attrs && !empty($attrs['href'])) return (string)$attrs['href'];
    }

    if (!empty($fallback)) return $fallback;

    $media = $item->children('media', true);
    if ($media && isset($media->thumbnail)) {
        $attrs = $media->thumbnail->attributes();
        if ($attrs && !empty($attrs['url'])) return (string)$attrs['url'];
    }

    return '';
}

// ===== Hent RSS (med cache) =====
$rss_snapshot_file = getenv('BOMOELLERSHOW_RSS_SNAPSHOT_FILE') ?: (dirname(__DIR__) . '/data/podcast-rss-fallback.xml');
$rss = fetch_rss_cached($rss_url, 900, $rss_snapshot_file); // cache i 15 min
if (!$rss) { http_response_code(500); die('<h2>Kunne ikke hente podcast-feedet.</h2>'); }

// Podcast cover - preferér itunes:image (typisk stor) først
$cover_image = '';
$itunes_ch = $rss->channel->children('itunes', true);
if ($itunes_ch && isset($itunes_ch->image)) {
    $attrs = $itunes_ch->image->attributes();
    if ($attrs && !empty($attrs['href'])) $cover_image = (string)$attrs['href'];
}
if ($cover_image === '' && isset($rss->channel->image->url)) {
    $cover_image = (string)$rss->channel->image->url;
}

// Det verificerede seed-katalog indeholder alle historiske episoder. Runtime-
// kataloget husker nye matches; Atom-feedet beriger de seneste med dato/views.
$youtube_disabled = getenv('BOMOELLERSHOW_DISABLE_YOUTUBE') === '1';
$youtube_catalog_seed = dirname(__DIR__) . '/data/youtube-catalog-seed.json';
$youtube_catalog_runtime = getenv('BOMOELLERSHOW_YOUTUBE_RUNTIME_FILE') ?: (dirname(__DIR__) . '/data/youtube-catalog-runtime.json');
$youtube_seed_catalog = $youtube_disabled ? [] : load_youtube_episode_catalog([$youtube_catalog_seed]);
$youtube_data = $youtube_disabled
    ? ['videos' => [], 'episodes' => []]
    // Runtime bidrager med nye matches, men seed indlæses sidst og er dermed
    // autoritativt for alle kuraterede episodenumre.
    : fetch_youtube_cached($youtube_feed_url, 900, [$youtube_catalog_runtime, $youtube_catalog_seed]);
$youtube_videos = $youtube_data['videos'];
$youtube_episode_catalog = $youtube_data['episodes'];
$youtube_by_id = [];
$youtube_by_title = [];
$youtube_by_date = [];
$youtube_by_episode_number = [];
foreach ($youtube_videos as $video) {
    $youtube_by_id[$video['id']] = $video;
    $youtube_by_title[$video['title_key']][] = $video;
    if ($video['date_iso'] !== '') $youtube_by_date[$video['date_iso']][] = $video;
    $video_episode_number = extract_youtube_episode_number($video['title']);
    if ($video_episode_number) $youtube_by_episode_number[$video_episode_number][] = $video;
}
$reserved_youtube_ids = [];
foreach ($youtube_episode_catalog as $catalog_video) $reserved_youtube_ids[$catalog_video['id']] = true;

// ===== Byg episodes =====
$episodes = [];
$slug_to_index = [];
$idx = 0;
$rss_item_count = count($rss->channel->item);

foreach ($rss->channel->item as $item) {
    $title = replace_em_dashes((string)$item->title);
    $pub_ts = strtotime((string)$item->pubDate);
    $date_iso = $pub_ts ? date('Y-m-d', $pub_ts) : '';
    $date_iso_full = $pub_ts ? date('c', $pub_ts) : ''; // fuld ISO8601 til OG/schema
    $date_human = $pub_ts ? date('d.m.Y', $pub_ts) : '';
    $desc_raw = replace_em_dashes((string)$item->description);

    $content_ns = $item->children('http://purl.org/rss/1.0/modules/content/');
    $content_encoded = isset($content_ns->encoded) ? replace_em_dashes((string)$content_ns->encoded) : '';

    $audio_url = get_audio_url_from_item($item);
    $ep_no = get_episode_number($item, $rss_item_count - $idx);

    $duration_seconds = parse_duration_seconds($item);
    $duration_label = format_duration($duration_seconds);

    $ep_image = get_episode_image_from_item($item, $cover_image);
    $youtube = null;
    $youtube_match_method = null;
    $title_key = normalize_match_title($title);

    // 1) Et permanent match vinder altid og beriges med nyere feed-metadata.
    if (isset($youtube_episode_catalog[$ep_no])) {
        $catalog_video = $youtube_episode_catalog[$ep_no];
        $youtube = isset($youtube_by_id[$catalog_video['id']])
            ? array_merge($catalog_video, $youtube_by_id[$catalog_video['id']])
            : $catalog_video;
        // Feedet må berige dato, titel og visninger, men aldrig overskrive en
        // thumbnail, som bevidst er kurateret i seed/runtime-kataloget.
        $youtube['thumbnail'] = $catalog_video['thumbnail'];
        $youtube_match_method = $catalog_video['match'] ?? 'catalog';
    }

    // 2) Eksakt normaliseret titel er sikkert, hvis videoen ikke allerede er låst.
    if (!$youtube && isset($youtube_by_title[$title_key])) {
        $exact_candidates = array_values(array_filter($youtube_by_title[$title_key], function ($video) use ($reserved_youtube_ids) {
            return empty($reserved_youtube_ids[$video['id']]);
        }));
        if (count($exact_candidates) === 1) {
            $youtube = $exact_candidates[0];
            $youtube_match_method = 'exact-title';
        }
    }

    // 3) Episodenummer i YouTube-titlen. Ved flere klip vælges kun en tydelig
    // titelvinder; ellers lader vi kataloget være urørt frem for at gætte.
    if (!$youtube && isset($youtube_by_episode_number[$ep_no])) {
        $number_candidates = array_values(array_filter($youtube_by_episode_number[$ep_no], function ($video) use ($reserved_youtube_ids) {
            return empty($reserved_youtube_ids[$video['id']]);
        }));
        $ranked = [];
        foreach ($number_candidates as $candidate) $ranked[] = ['score' => youtube_title_similarity($title, $candidate['title']), 'video' => $candidate];
        usort($ranked, function ($a, $b) { return $b['score'] <=> $a['score']; });
        if (count($ranked) === 1 || (!empty($ranked) && $ranked[0]['score'] >= 0.22 && ($ranked[0]['score'] - ($ranked[1]['score'] ?? 0)) >= 0.06)) {
            $youtube = $ranked[0]['video'];
            $youtube_match_method = 'episode-number';
        }
    }

    // 4) Dato ±2 dage kombineret med titellighed. Dato alene må ikke vælge
    // mellem et hovedafsnit og flere klip udgivet samme dag.
    if (!$youtube && $date_iso !== '') {
        $date_ranked = [];
        $episode_day = strtotime($date_iso . ' 12:00:00 UTC');
        foreach ($youtube_videos as $candidate) {
            if (!empty($reserved_youtube_ids[$candidate['id']]) || empty($candidate['date_iso'])) continue;
            $video_day = strtotime($candidate['date_iso'] . ' 12:00:00 UTC');
            $days_apart = abs((int)round(($video_day - $episode_day) / 86400));
            if ($days_apart > 2) continue;
            $score = youtube_title_similarity($title, $candidate['title']) + (2 - $days_apart) * 0.06;
            $date_ranked[] = ['score' => $score, 'video' => $candidate];
        }
        usort($date_ranked, function ($a, $b) { return $b['score'] <=> $a['score']; });
        if (!empty($date_ranked) && $date_ranked[0]['score'] >= 0.20 && (count($date_ranked) === 1 || ($date_ranked[0]['score'] - $date_ranked[1]['score']) >= 0.06)) {
            $youtube = $date_ranked[0]['video'];
            $youtube_match_method = 'date-and-title';
        }
    }

    if ($youtube) {
        $youtube['match'] = $youtube_match_method ?: 'catalog';
        $youtube_episode_catalog[$ep_no] = $youtube;
        $reserved_youtube_ids[$youtube['id']] = true;
    }

    $episode_content = $content_encoded ?: $desc_raw;

    $short_title = short_title_for_slug($title);
    $base_slug = $ep_no ? ($ep_no . '-' . $short_title) : $short_title;
    $slug = dk_slugify($base_slug);

    $original_slug = $slug; $dupe = 2;
    while (isset($slug_to_index[$slug])) { $slug = $original_slug . '-' . $dupe; $dupe++; }

    $episodes[] = [
        'title'       => $title,
        'slug'        => $slug,
        'date_human'  => $date_human,
        'date_iso'    => $date_iso,
        'date_iso_full' => $date_iso_full,
        'content'     => $episode_content,
        'audio_url'   => $audio_url,
        'ep_no'       => $ep_no,
        'duration_s'  => $duration_seconds,
        'duration'    => $duration_label,
        'image'       => $ep_image,
        'youtube_id'  => $youtube ? $youtube['id'] : null,
        'youtube_thumbnail' => $youtube ? $youtube['thumbnail'] : null,
        'youtube_views' => $youtube ? $youtube['views'] : 0,
        'youtube_published' => $youtube ? $youtube['published_iso'] : null,
        'topics'      => episode_topics($title, $episode_content),
        'idx'         => $idx,
    ];
    $slug_to_index[$slug] = $idx;
    $idx++;
}
$total = count($episodes);

// Anvend evt. manuelle billed-overrides (efter episodenumre er endeligt sat)
foreach ($episodes as &$ep_ref) {
    if (isset($episode_image_overrides[(int)$ep_ref['ep_no']])) {
        $ep_ref['image'] = $episode_image_overrides[(int)$ep_ref['ep_no']];
    }
}
unset($ep_ref);

// Gem den samlede, berigede tabel. Hvis webhotellet er read-only, fortsætter
// seed-kataloget og temp-cachen uændret med at virke.
if (!$youtube_disabled) {
    // Runtime-filen ejer kun automatisk fundne episoder. Seed-data gemmes ikke
    // som kopier, så en bevidst sletning fra seed ikke kan genopstå fra runtime.
    $youtube_runtime_only_catalog = array_diff_key($youtube_episode_catalog, $youtube_seed_catalog);
    save_youtube_episode_catalog($youtube_catalog_runtime, $youtube_channel_id, $youtube_runtime_only_catalog);
}

$popular_episodes = array_values(array_filter($episodes, function ($ep) {
    return !empty($ep['youtube_id']) && (int)$ep['youtube_views'] > 0;
}));
usort($popular_episodes, function ($a, $b) {
    return (int)$b['youtube_views'] <=> (int)$a['youtube_views'];
});
$popular_episodes = array_slice($popular_episodes, 0, 4);

$topic_order = ['Iværksætteri', 'Business', 'Teknologi', 'Produkt', 'Livet', 'Tanker'];
$topic_counts = [];
foreach ($episodes as $ep) {
    foreach ($ep['topics'] as $topic) {
        $topic_counts[$topic] = ($topic_counts[$topic] ?? 0) + 1;
    }
}
$all_topics = array_values(array_filter($topic_order, function ($topic) use ($topic_counts) {
    return !empty($topic_counts[$topic]);
}));

// ===== Routing =====
$request_uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$path = rtrim($request_uri, '/');

// Én kanonisk form pr. URL: gamle links med afsluttende slash flyttes permanent.
if ($request_uri !== '/' && substr($request_uri, -1) === '/') {
    header('Location: ' . rtrim($site_url, '/') . $path, true, 301);
    exit;
}

// --- SITEMAP ---
if (preg_match('#^/sitemap\.xml$#', $request_uri)) {
    header('Content-Type: application/xml; charset=UTF-8');
    $base = rtrim($site_url, '/');
    $latest_iso = '';
    foreach ($episodes as $ep) {
        if ($ep['date_iso'] && $ep['date_iso'] > $latest_iso) $latest_iso = $ep['date_iso'];
    }
    if ($latest_iso === '') $latest_iso = date('Y-m-d');

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' .
         ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($base . '/', ENT_XML1) . '</loc>' . "\n";
    echo '    <lastmod>' . htmlspecialchars($latest_iso) . '</lastmod>' . "\n";
    echo '    <changefreq>weekly</changefreq>' . "\n";
    echo '    <priority>1.0</priority>' . "\n";
    echo '  </url>' . "\n";

    foreach ($hosts as $h) {
        echo '  <url>' . "\n";
        echo '    <loc>' . htmlspecialchars($base . '/vaert/' . rawurlencode($h['slug']), ENT_XML1) . '</loc>' . "\n";
        echo '    <lastmod>' . htmlspecialchars($latest_iso) . '</lastmod>' . "\n";
        echo '    <changefreq>monthly</changefreq>' . "\n";
        echo '    <priority>0.7</priority>' . "\n";
        if (!empty($h['image'])) {
            echo '    <image:image>' . "\n";
            echo '      <image:loc>' . htmlspecialchars($base . $h['image'], ENT_XML1) . '</image:loc>' . "\n";
            echo '      <image:title>' . htmlspecialchars($h['name'] . ', podcastvært på ' . $site_name, ENT_XML1) . '</image:title>' . "\n";
            echo '    </image:image>' . "\n";
        }
        echo '  </url>' . "\n";
    }

    foreach ($episodes as $ep) {
        $loc = $base . '/episode/' . rawurlencode($ep['slug']);
        $lastmod = $ep['date_iso'] ?: $latest_iso;
        echo '  <url>' . "\n";
        echo '    <loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>' . "\n";
        echo '    <lastmod>' . htmlspecialchars($lastmod) . '</lastmod>' . "\n";
        echo '    <changefreq>monthly</changefreq>' . "\n";
        echo '    <priority>0.8</priority>' . "\n";
        $sitemap_image = $ep['youtube_thumbnail'] ?: $ep['image'];
        if (!empty($sitemap_image)) {
            echo '    <image:image>' . "\n";
            echo '      <image:loc>' . htmlspecialchars($sitemap_image, ENT_XML1) . '</image:loc>' . "\n";
            echo '      <image:title>' . htmlspecialchars($ep['title'], ENT_XML1) . '</image:title>' . "\n";
            echo '    </image:image>' . "\n";
        }
        echo '  </url>' . "\n";
    }
    echo '</urlset>';
    exit;
}

// --- LLMS.TXT (GEO / AI-crawlers) ---
if (preg_match('#^/llms\.txt$#', $request_uri)) {
    header('Content-Type: text/plain; charset=UTF-8');
    $base = rtrim($site_url, '/');
    $L = [];
    $L[] = '# ' . $site_name;
    $L[] = '';
    $L[] = '> ' . $series_description;
    $L[] = '';
    $L[] = 'I Bo Møller showet deler jeg personlige refleksioner optaget på farten. '
         . 'Jeg taler om alt fra iværksætteri, business og software til livet. Sproget er dansk.';
    $L[] = '';
    $L[] = '## Vært';
    foreach ($hosts as $h) {
        $profile_url = $base . '/vaert/' . rawurlencode($h['slug']);
        $L[] = '- [' . $h['name'] . '](' . $profile_url . ') - ' . $h['long_bio'];
    }
    $L[] = '';
    $L[] = '## Episoder';
    foreach ($episodes as $ep) {
        $loc = $base . '/episode/' . rawurlencode($ep['slug']);
        // Titel skal ikke kunne bryde markdown-linket: erstat []-tegn og fold whitespace
        $safe_title = trim(preg_replace('/\s+/', ' ', strtr($ep['title'], ['[' => '(', ']' => ')'])));
        $date_part = $ep['date_iso'] ? ' (' . $ep['date_iso'] . ')' : '';
        $L[] = '- [Episode ' . (int)$ep['ep_no'] . ': ' . $safe_title . '](' . $loc . ')' . $date_part . ': ' . teaser($ep['content'], 160);
    }
    $L[] = '';
    $L[] = '## Lyt';
    foreach ($platforms as $name => $url) {
        $L[] = '- ' . $name . ': ' . $url;
    }
    $L[] = '';
    echo implode("\n", $L);
    exit;
}

// --- Episode / vært / forside / 404 ---
$is_single = false; $requested_slug = null; $is_host = false; $host_profile = null; $is_404 = false;

if ($path === '' || $path === '/') {
    // forside
} elseif (preg_match('#^/e/(\d+)$#', $path, $m)) {
    // Kort del-link: /e/63 -> 301 til den fulde episode-URL
    $wanted = (int)$m[1];
    foreach ($episodes as $ep) {
        if ((int)$ep['ep_no'] === $wanted) {
            header('Location: ' . rtrim($site_url, '/') . '/episode/' . rawurlencode($ep['slug']), true, 301);
            exit;
        }
    }
    http_response_code(404);
    $is_404 = true;
} elseif (preg_match('#^/episode/([a-z0-9\-]+)$#', $path, $m)) {
    $requested_slug = $m[1];
    if (isset($slug_to_index[$requested_slug])) {
        $is_single = true;
    } else {
        // Slugs indeholder episodenummeret. Hvis en titel ændres i RSS, sender
        // vi alle tidligere titel-varianter permanent til den nye canonical URL.
        $legacy_episode_number = preg_match('/^(\d+)(?:-|$)/', $requested_slug, $legacy_match)
            ? (int)$legacy_match[1]
            : 0;
        $legacy_target = null;
        foreach ($episodes as $ep) {
            if ($legacy_episode_number > 0 && (int)$ep['ep_no'] === $legacy_episode_number) {
                $legacy_target = $ep;
                break;
            }
        }
        if ($legacy_target) {
            header('Location: ' . rtrim($site_url, '/') . '/episode/' . rawurlencode($legacy_target['slug']), true, 301);
            exit;
        } else {
            http_response_code(404);
            $is_404 = true;
        }
    }
} elseif (preg_match('#^/vaert/([a-z0-9\-]+)$#', $path, $m)) {
    foreach ($hosts as $host) {
        if ($host['slug'] === $m[1]) {
            $is_host = true;
            $host_profile = $host;
            break;
        }
    }
    if (!$is_host) {
        http_response_code(404);
        $is_404 = true;
    }
} else {
    http_response_code(404);
    $is_404 = true;
}

// ===== SEO / OG =====
$page_title = $site_name . ' – tanker på farten';
$page_description = $series_description;
$page_url = rtrim($site_url, '/') . '/';
$og_image = $cover_image;
$og_image_width = 1400;
$og_image_height = 1400;
$social_title = null;

$single = null; $prev_link = null; $prev_label = null; $next_link = null; $next_label = null;

if ($is_single) {
    $i = $slug_to_index[$requested_slug];
    $single = $episodes[$i];

    $page_title = teaser($single['title'], 52) . ' – ' . $site_name;
    $social_title = $single['title'] . ' – ' . $site_name;
    $page_description = teaser($single['content'], 160);
    $page_url = rtrim($site_url, '/') . '/episode/' . rawurlencode($single['slug']);

    $og_image = !empty($single['youtube_thumbnail'])
        ? $single['youtube_thumbnail']
        : (!empty($single['image']) ? $single['image'] : $cover_image);
    if (!empty($single['youtube_thumbnail'])) {
        list($og_image_width, $og_image_height) = youtube_thumbnail_dimensions($single['youtube_thumbnail']);
    }

    $curr_no = (int)$single['ep_no'];
    $lower = null; $higher = null;
    foreach ($episodes as $ep) {
        if ((int)$ep['ep_no'] < $curr_no) {
            if ($lower === null || (int)$ep['ep_no'] > (int)$lower['ep_no']) $lower = $ep;
        } elseif ((int)$ep['ep_no'] > $curr_no) {
            if ($higher === null || (int)$ep['ep_no'] < (int)$higher['ep_no']) $higher = $ep;
        }
    }
    if ($lower) { $prev_link  = '/episode/' . htmlspecialchars($lower['slug']);  $prev_label = '← Episode ' . (int)$lower['ep_no']; }
    if ($higher){ $next_link  = '/episode/' . htmlspecialchars($higher['slug']); $next_label = 'Episode ' . (int)$higher['ep_no'] . ' →'; }
} elseif ($is_host && $host_profile) {
    $page_title = 'Om mig – ' . $site_name;
    $page_description = teaser($host_profile['long_bio'], 160);
    $page_url = rtrim($site_url, '/') . '/vaert/' . rawurlencode($host_profile['slug']);
    $og_image = rtrim($site_url, '/') . $host_profile['image'];
    $og_image_width = (int)$host_profile['image_width'];
    $og_image_height = (int)$host_profile['image_height'];
} elseif ($is_404) {
    $page_title = '404 – Siden findes ikke · ' . $site_name;
    $page_description = "Jeg kan ikke finde den side. Måske leder du efter en af mine podcast-episoder?";
    // $page_url bevares som forsiden (default) – en 404 skal ikke kanonisere til sig selv.
}
$social_title = $social_title ?: $page_title;
$og_image_type = image_mime_type_from_url($og_image);

// ===== JSON-LD (schema.org) =====
$ld_base = rtrim($site_url, '/');
$ld_persons = [];
$ld_person_by_slug = [];
foreach ($hosts as $h) {
    $profile_url = $ld_base . '/vaert/' . rawurlencode($h['slug']);
    $p = [
        '@type'       => 'Person',
        '@id'         => $profile_url . '#person',
        'name'        => $h['name'],
        'url'         => $profile_url,
        'description' => $h['long_bio'],
        'jobTitle'    => $h['job_title'],
        'image'       => [
            '@type'  => 'ImageObject',
            'url'    => $ld_base . $h['image'],
            'width'  => (int)$h['image_width'],
            'height' => (int)$h['image_height'],
        ],
        'knowsAbout'  => $h['knowsAbout'],
        'sameAs'      => $h['sameAs'],
        'affiliation' => array_map(function ($company) {
            return ['@type' => 'Organization', 'name' => $company['name'], 'url' => $company['url']];
        }, $h['companies']),
    ];
    $ld_persons[] = $p;
    $ld_person_by_slug[$h['slug']] = $p;
}
$ld_author_refs = array_map(function ($person) {
    return ['@type' => 'Person', '@id' => $person['@id'], 'name' => $person['name'], 'url' => $person['url']];
}, $ld_persons);
$ld_series_ref = ['@type' => 'PodcastSeries', '@id' => $ld_base . '/#podcast', 'name' => $site_name, 'url' => $ld_base . '/'];
$ld_website_ref = ['@type' => 'WebSite', '@id' => $ld_base . '/#website', 'name' => $site_name, 'url' => $ld_base . '/'];

$ld_graph = [];
if ($is_single && $single) {
    $episode_id = $page_url . '#episode';
    $webpage_id = $page_url . '#webpage';
    $episode_node = [
        '@type'         => 'PodcastEpisode',
        '@id'           => $episode_id,
        'url'           => $page_url,
        'name'          => $single['title'],
        'datePublished' => $single['date_iso_full'] ?: ($single['date_iso'] ?: null),
        'episodeNumber' => (int)$single['ep_no'],
        'timeRequired'  => iso8601_duration($single['duration_s']),
        'description'   => $page_description,
        'image'         => $og_image ?: null,
        'inLanguage'    => 'da-DK',
        'partOfSeries'  => $ld_series_ref,
        'author'        => $ld_author_refs,
        'mainEntityOfPage' => ['@id' => $webpage_id],
    ];
    if (!empty($single['audio_url'])) {
        $episode_node['associatedMedia'] = [
            '@type'          => 'AudioObject',
            'contentUrl'     => $single['audio_url'],
            'encodingFormat' => 'audio/mpeg',
        ];
    }
    $ld_graph[] = $episode_node;
    $ld_graph[] = [
        '@type'       => 'WebPage',
        '@id'         => $webpage_id,
        'url'         => $page_url,
        'name'        => $page_title,
        'description' => $page_description,
        'inLanguage'  => 'da-DK',
        'isPartOf'    => $ld_website_ref,
        'mainEntity'  => ['@id' => $episode_id],
    ];
    if (!empty($single['youtube_id'])) {
        $video_node = [
            '@type'        => 'VideoObject',
            '@id'          => $page_url . '#video',
            'name'         => $single['title'],
            'description'  => $page_description,
            'thumbnailUrl' => $single['youtube_thumbnail'],
            'uploadDate'   => $single['youtube_published'] ?: ($single['date_iso_full'] ?: $single['date_iso']),
            'duration'     => iso8601_duration($single['duration_s']),
            'embedUrl'     => 'https://www.youtube-nocookie.com/embed/' . rawurlencode($single['youtube_id']),
            'url'          => 'https://www.youtube.com/watch?v=' . rawurlencode($single['youtube_id']),
            'inLanguage'   => 'da-DK',
            'isPartOf'     => ['@id' => $episode_id],
        ];
        if ((int)$single['youtube_views'] > 0) {
            $video_node['interactionStatistic'] = [
                '@type' => 'InteractionCounter',
                'interactionType' => ['@type' => 'WatchAction'],
                'userInteractionCount' => (int)$single['youtube_views'],
            ];
        }
        $ld_graph[] = $video_node;
    }
    $ld_graph[] = [
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Forside', 'item' => $ld_base . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Episode ' . (int)$single['ep_no'], 'item' => $page_url],
        ],
    ];
} elseif ($is_host && $host_profile) {
    $profile_person = $ld_person_by_slug[$host_profile['slug']];
    $profile_episodes = [];
    foreach (array_slice($episodes, 0, 12) as $ep) {
        $profile_episodes[] = [
            '@type' => 'PodcastEpisode',
            '@id' => $ld_base . '/episode/' . rawurlencode($ep['slug']) . '#episode',
            'name' => $ep['title'],
            'url' => $ld_base . '/episode/' . rawurlencode($ep['slug']),
            'datePublished' => $ep['date_iso_full'] ?: $ep['date_iso'],
            'author' => ['@id' => $profile_person['@id']],
        ];
    }
    $ld_graph[] = [
        '@type'       => 'ProfilePage',
        '@id'         => $page_url . '#profilepage',
        'url'         => $page_url,
        'name'        => $page_title,
        'description' => $page_description,
        'inLanguage'  => 'da-DK',
        'isPartOf'    => $ld_website_ref,
        'mainEntity'  => $profile_person,
        'hasPart'     => $profile_episodes,
    ];
    $ld_graph[] = [
        '@type'           => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Forside', 'item' => $ld_base . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Om mig', 'item' => $ld_base . '/#om-bo'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $host_profile['name'], 'item' => $page_url],
        ],
    ];
} elseif (!$is_404) {
    $ld_graph[] = [
        '@type'       => 'WebSite',
        '@id'         => $ld_base . '/#website',
        'name'        => $site_name,
        'url'         => $ld_base . '/',
        'description' => $series_description,
        'inLanguage'  => 'da-DK',
        'about'       => $ld_series_ref,
    ];
    $ld_graph[] = [
        '@type'       => 'PodcastSeries',
        '@id'         => $ld_base . '/#podcast',
        'name'        => $site_name,
        'url'         => $ld_base . '/',
        'description' => $series_description,
        'inLanguage'  => 'da-DK',
        'image'       => $cover_image ?: null,
        'webFeed'     => $public_rss_url,
        'sameAs'      => $platform_profiles,
        'author'      => $ld_author_refs,
    ];
    foreach ($ld_persons as $person) $ld_graph[] = $person;
}

$json_ld = null;
if ($ld_graph) {
    $doc = (count($ld_graph) === 1)
        ? array_merge(['@context' => 'https://schema.org'], $ld_graph[0])
        : ['@context' => 'https://schema.org', '@graph' => $ld_graph];
    // Slashes escapes IKKE-unescaped, så "</script>" i data ikke kan bryde ud af <script>.
    $json_ld = json_encode(ld_clean($doc), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

// ===== View =====
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($page_title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= htmlspecialchars($page_description) ?>">
    <?php if ($is_single): ?><meta name="author" content="<?= htmlspecialchars(implode(', ', array_column($hosts, 'name'))) ?>"><?php endif; ?>
    <?php if ($is_host && $host_profile): ?><meta name="author" content="<?= htmlspecialchars($host_profile['name']) ?>"><?php endif; ?>
    <?php if ($is_404): ?>
    <meta name="robots" content="noindex">
    <?php else: ?>
    <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">
    <link rel="canonical" href="<?= htmlspecialchars($page_url) ?>">
    <?php endif; ?>

    <?php if ($is_single && $prev_link): ?><link rel="prev" href="<?= $prev_link ?>"><?php endif; ?>
    <?php if ($is_single && $next_link): ?><link rel="next" href="<?= $next_link ?>"><?php endif; ?>

    <link rel="icon" href="/assets/bo-avatar.jpg?v=1" type="image/jpeg">
    <link rel="manifest" href="/site.webmanifest">
    <?php if ($youtube_videos): ?><link rel="preconnect" href="https://i.ytimg.com" crossorigin><?php endif; ?>
    <?php if ($cover_image): ?><link rel="preconnect" href="https://d3t3ozftmdmh3i.cloudfront.net" crossorigin><?php endif; ?>
    <meta name="theme-color" content="#101214">

    <!-- Podcast-feed (gør RSS-feedet synligt for feed-læsere og podcast-crawlere) -->
    <link rel="alternate" type="application/rss+xml" title="<?= htmlspecialchars($site_name) ?>" href="<?= htmlspecialchars($public_rss_url) ?>">

    <!-- Open Graph -->
    <meta property="og:type" content="<?= $is_single ? 'article' : ($is_host ? 'profile' : 'website') ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars($site_name) ?>">
    <meta property="og:locale" content="da_DK">
    <meta property="og:url" content="<?= htmlspecialchars($page_url) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($social_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($page_description) ?>">
    <?php if (!empty($og_image)): ?>
      <meta property="og:image" content="<?= htmlspecialchars($og_image) ?>">
      <meta property="og:image:secure_url" content="<?= htmlspecialchars($og_image) ?>">
      <?php if ($og_image_type): ?><meta property="og:image:type" content="<?= htmlspecialchars($og_image_type) ?>"><?php endif; ?>
      <meta property="og:image:alt" content="<?= htmlspecialchars($social_title) ?>">
      <meta property="og:image:width" content="<?= (int)$og_image_width ?>"><meta property="og:image:height" content="<?= (int)$og_image_height ?>">
    <?php endif; ?>
    <?php if ($is_single && $single): ?>
      <?php $pub_og = $single['date_iso_full'] ?: $single['date_iso']; ?>
      <?php if (!empty($pub_og)): ?><meta property="article:published_time" content="<?= htmlspecialchars($pub_og) ?>"><?php endif; ?>
      <?php foreach ($hosts as $h): ?><meta property="article:author" content="<?= htmlspecialchars(rtrim($site_url, '/') . '/vaert/' . $h['slug']) ?>"><?php endforeach; ?>
      <?php if (!empty($single['audio_url'])): ?><meta property="og:audio" content="<?= htmlspecialchars($single['audio_url']) ?>"><meta property="og:audio:type" content="audio/mpeg"><?php endif; ?>
      <?php if (!empty($single['youtube_id'])): ?><meta property="og:video" content="<?= htmlspecialchars('https://www.youtube.com/embed/' . $single['youtube_id']) ?>"><meta property="og:video:type" content="text/html"><meta property="og:video:width" content="1280"><meta property="og:video:height" content="720"><?php endif; ?>
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($social_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($page_description) ?>">
    <?php if (!empty($og_image)): ?><meta name="twitter:image" content="<?= htmlspecialchars($og_image) ?>"><?php endif; ?>
    <meta name="twitter:image:alt" content="<?= htmlspecialchars($social_title) ?>">

    <?php if ($json_ld): ?>
    <script type="application/ld+json">
<?= $json_ld ?>
</script>
    <?php endif; ?>

    <?php if (false): // Legacy-CSS beholdes kun midlertidigt i kilden; det sendes ikke til browseren. ?>
    <style>
        html, body { background:#fff; color:#111; font-family:'Inter', Arial, sans-serif; margin:0; padding:0; }
        .container { max-width:680px; margin:40px auto; padding:24px; }

        /* Forside-cover */
        .podcast-cover { width:160px; height:160px; object-fit:cover; border-radius:18px; box-shadow:0 4px 24px rgba(0,0,0,0.07); display:block; margin:0 auto 18px; }

        /* ✅ Links (forsiden) – tilbage til oprindelig styling */
        .links { text-align:center; margin-bottom:34px; }
        .plink { display:inline-block; margin:0 6px; padding:8px 18px; border-radius:999px; border:1px solid #eee; background:#fafafd; color:#111; text-decoration:none; font-size:1rem; transition:background .18s; font-weight:500; }
        .plink:hover { background:#f3f3f9; }

        /* Episode-cover på single (fuld bredde) */
        .episode-hero-wrap { display:flex; justify-content:flex-start; margin:14px 0 12px; }
        .episode-hero {
            width:100%;
            aspect-ratio:1 / 1;
            object-fit:cover;
            border-radius:20px;
            box-shadow:0 10px 40px rgba(0,0,0,0.10);
            border:1px solid rgba(0,0,0,0.06);
            background:#fff;
            display:block;
        }

        h1 { font-size:2.3rem; margin-bottom:6px; font-weight:600; letter-spacing:-1px; text-align:center; }
        .desc { color:#555; font-size:1.1rem; margin-bottom:32px; text-align:center; }

        /* Episode-listen: hele kortet klikbart */
        .episode { margin:0; padding:0; border-bottom:1px solid #eee; }
        .episode:last-child { border-bottom:none; }
        .episode-card { display:block; padding:18px 14px; text-decoration:none; color:inherit; transition: transform .15s ease, box-shadow .15s ease, background .15s ease; border-radius:12px; margin:6px -6px; }
        .episode-card:hover { background:#fafbff; box-shadow:0 6px 24px rgba(30,60,150,0.07); transform: translateY(-1px); }
        .episode-title { font-size:1.18rem; font-weight:600; margin:0 0 6px; }
        .episode-date { color:#888; font-size:.96rem; margin-bottom:6px; }
        .teaser { margin-top:2px; color:#333; font-size:1.0rem; }

        /* Single */
        .single h1 { text-align:left; font-size:1.9rem; }
        .back { display:inline-block; margin-bottom:14px; text-decoration:none; color:#2546af; }
        .back:hover { text-decoration:underline; }

        /* Brødkrummesti */
        .crumbs { font-size:.92rem; color:#888; margin-bottom:12px; }
        .crumbs a { color:#2546af; text-decoration:none; }
        .crumbs a:hover { text-decoration:underline; }
        .crumbs span { margin:0 2px; }

        /* Relaterede episoder */
        .related { margin-top:34px; }
        .related h2 { font-size:1.15rem; font-weight:600; margin:0 0 12px; }
        .related-item { display:flex; align-items:baseline; gap:10px; padding:10px 12px; margin:6px 0; border:1px solid #eee; border-radius:12px; text-decoration:none; color:inherit; transition:background .15s ease, border-color .15s ease; }
        .related-item:hover { background:#fafbff; border-color:#d4dcff; }
        .related-no { flex:0 0 auto; font-weight:600; color:#2546af; font-size:.92rem; }
        .related-title { color:#222; font-size:1rem; }
        .single .meta { color:#666; margin-bottom:6px; }
        .single .content { margin-top:12px; color:#333; font-size:1.03rem; line-height:1.55; }

        /* Del-link (kort URL) */
        .share-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:8px 0 4px; color:#666; font-size:.95rem; }
        .share-url { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; background:#f4f6ff; border:1px solid #d3e2ff; border-radius:8px; padding:4px 10px; color:#2546af; }
        .copy-btn { cursor:pointer; border:1px solid #d3e2ff; background:#fff; color:#2546af; border-radius:8px; padding:4px 10px; font-size:.9rem; font-weight:600; transition:background .15s ease; }
        .copy-btn:hover { background:#eef2ff; }

        /* Player + platform links på én linje */
        .player-row{
          display:flex;
          align-items:center;
          gap:12px;
          margin:12px 0 12px;
          padding:10px 12px;
          border:1px solid #e5e7eb;
          background:#fafbff;
          border-radius:16px;
          box-shadow:0 1px 10px rgba(30,60,150,0.05);
        }
        .player-row audio{
          flex:1;
          width:100%;
          height:34px;
        }
        .platform-links{
          display:flex;
          gap:8px;
          flex-wrap:nowrap;
        }
        .pill{
          display:inline-flex;
          align-items:center;
          gap:8px;
          padding:8px 12px;
          border-radius:999px;
          border:1px solid #e5e7eb;
          background:#fff;
          color:#111;
          text-decoration:none;
          font-weight:600;
          font-size:.95rem;
          line-height:1;
          transition: background .15s ease, transform .15s ease, box-shadow .15s ease;
          box-shadow:0 1px 6px rgba(0,0,0,0.04);
        }
        .pill:hover{
          background:#f3f6ff;
          transform: translateY(-1px);
        }
        .pill .dot{
          width:9px;
          height:9px;
          border-radius:99px;
          background:#2546af;
          display:inline-block;
          box-shadow:0 0 0 3px rgba(37,70,175,0.10);
        }

        /* Nav-knapper 50/50 */
        .nav-ep { display:flex; gap:10px; margin-top:22px; }
        .nav-btn { flex:1; display:block; text-decoration:none; padding:12px 14px; border-radius:12px; border:1px solid #e5e7eb; background:#f9fafb; color:#2546af; font-weight:500; transition: background .15s ease, border-color .15s ease; }
        .nav-btn:hover { background:#eef2ff; border-color:#d4dcff; }
        .nav-btn.disabled { color:#5f6368; background:#f9fafb; pointer-events:none; }
        .nav-btn.left { text-align:left; }
        .nav-btn.right { text-align:right; }

        /* CTA og Bio */
        .newsletter-cta { width:100%; margin:32px 0 18px; padding:14px 0 7px; border-radius:16px; background:#f4f6ff; border:1px solid #d3e2ff; box-shadow:0 1px 8px rgba(80,100,200,0.04); text-align:center; }
        .cta-title { font-size:1.17rem; font-weight:600; color:#2546af; margin-bottom:10px; }
        .cta-buttons { margin-bottom:7px; }
        .cta-btn { display:inline-block; margin:0 10px 7px; padding:9px 20px; background:#2546af; color:#fff; border:none; border-radius:999px; font-size:1rem; font-weight:500; text-decoration:none; transition:background .17s; box-shadow:0 1px 5px rgba(80,100,200,0.03); }
        .cta-btn:hover { background:#112477; }
        .cta-desc { color:#325; font-size:1.01rem; margin-top:5px; opacity:.88; }

        .hosts-bio { margin:52px auto 24px; padding:22px 18px 10px; max-width:660px; border-radius:14px; background:#fafbfc; border:1px solid #eee; font-size:1.06rem; color:#444; box-shadow:0 1px 8px rgba(20,30,60,0.04); display:flex; flex-wrap:wrap; gap:30px; justify-content:space-between; }
        .host { flex:1 1 260px; min-width:200px; margin-bottom:8px; }
        .host-name { font-weight:600; font-size:1.12rem; margin-bottom:3px; color:#222; }
        .host-desc a { color:#2a77ff; text-decoration:none; border-bottom:1px dotted #b0d1ff; transition:border-color .2s; }
        .host-desc a:hover { border-bottom:1px solid #2a77ff; }

        /* 404 side */
        .http404 { text-align:center; padding:8px 0 2px; }
        .http404 h1 { font-size:2.1rem; }
        .oops { font-size:4.2rem; line-height:1; margin:10px 0 0; letter-spacing:-2px; }
        .joke { color:#444; font-size:1.08rem; margin:10px auto 18px; max-width:540px; }
        .home-btn { display:inline-block; margin-top:6px; padding:10px 18px; border-radius:12px; border:1px solid #e5e7eb; background:#f9fafb; text-decoration:none; color:#2546af; font-weight:500; }
        .home-btn:hover { background:#eef2ff; border-color:#d4dcff; }
        .suggest { margin-top:20px; color:#666; }
        .suggest-list a { display:inline-block; margin:6px 6px 0 0; padding:6px 10px; border-radius:999px; border:1px solid #eee; text-decoration:none; color:#333; }
        .suggest-list a:hover { background:#fafbff; }

        @media (max-width:700px){
            .container { padding:12px; }
            .podcast-cover { width:110px; height:110px; }
            h1 { font-size:1.5rem; }
            .hosts-bio { flex-direction:column; gap:18px; padding:14px 6px 7px; }
            .newsletter-cta { padding:7px 0 4px; }
            .cta-title { font-size:1.04rem; }
            .cta-btn { padding:7px 12px; font-size:.98rem; }

            .player-row{ flex-direction:column; align-items:stretch; }
            .platform-links{ flex-wrap:wrap; justify-content:flex-start; }

            .episode-hero{ height:240px; }
        }
    </style>
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/site-v2.css?v=12">

</head>
<body>
<div class="site-shell">
  <header class="site-header">
    <a class="brand" href="/" aria-label="<?= htmlspecialchars($site_name) ?> – forside">
      <img class="brand-mark" src="/assets/bo-avatar.jpg" width="42" height="42" alt="">
      <span>BO MØLLER <em>/ SHOWET</em></span>
    </a>
    <nav class="site-nav" aria-label="Hovednavigation">
      <a href="/#episoder">Episoder</a>
      <a href="/#om-bo">Om mig</a>
      <a class="listen-nav" href="<?= htmlspecialchars($platforms['Spotify']) ?>" target="_blank" rel="noopener" aria-label="Lyt til Bo Møller showet på Spotify">Lyt <span aria-hidden="true">↗</span></a>
    </nav>
  </header>

  <main class="container <?= $is_single ? 'single' : ($is_host ? 'host-page' : '') ?>">
  <?php if (!$is_single && !$is_host && !$is_404): ?>
    <?php $latest = $episodes[0] ?? null; ?>
    <section class="home-hero" aria-labelledby="show-title">
      <div class="hero-copy">
        <span class="hero-kicker"><span aria-hidden="true">bo@showet:~$</span> ./start</span>
        <h1 id="show-title">Bo Møller <span>showet</span></h1>
        <p class="hero-intro">Her deler jeg ufiltrerede tanker om at bygge virksomheder, bruge teknologi og finde mening i det hele - optaget fra bilen, gåturen og hverdagen.</p>
        <div class="hero-actions">
          <?php if ($latest): ?><a class="button button-primary" href="<?= '/episode/' . htmlspecialchars($latest['slug']) ?>">Seneste episode <span aria-hidden="true">→</span></a><?php endif; ?>
        </div>
      </div>
    </section>

    <section class="section" id="episoder" aria-labelledby="episodes-title">
      <h2 class="sr-only" id="episodes-title">Episoder</h2>
      <div class="episode-grid" id="episode-grid">
        <?php foreach ($episodes as $ep): ?>
        <?php $thumb = $ep['youtube_thumbnail'] ?: ($ep['image'] ?: $cover_image); ?>
        <a class="episode-card-v2" href="<?= '/episode/' . htmlspecialchars($ep['slug']) ?>"
           data-search="<?= htmlspecialchars(mb_strtolower(strip_tags($ep['title'] . ' ' . teaser($ep['content'], 500, false)), 'UTF-8')) ?>"
           data-topics="<?= htmlspecialchars(mb_strtolower(implode('|', $ep['topics']), 'UTF-8')) ?>">
          <div class="card-media">
            <?php if ($thumb): ?><img class="<?= empty($ep['youtube_thumbnail']) ? 'is-cover' : '' ?>" src="<?= htmlspecialchars($thumb) ?>"<?php if ($ep['youtube_id']): ?> srcset="<?= htmlspecialchars(youtube_thumbnail_srcset($ep['youtube_id'], $ep['youtube_thumbnail'])) ?>" sizes="(max-width: 650px) 126px, (max-width: 900px) calc(50vw - 30px), 370px"<?php endif; ?> loading="lazy" decoding="async" width="640" height="360" alt=""><?php endif; ?>
            <?php if (!empty($ep['youtube_id'])): ?><span class="video-badge">▶ Video</span><?php endif; ?>
          </div>
          <div class="card-body">
            <div class="card-topics"><?php foreach (array_slice($ep['topics'], 0, 2) as $topic): ?><span class="topic"><?= htmlspecialchars($topic) ?></span><?php endforeach; ?></div>
            <h3><?= htmlspecialchars($ep['title']) ?></h3>
            <div class="card-teaser"><?= htmlspecialchars(teaser($ep['content'], 150)) ?></div>
            <div class="card-meta">Ep. <?= (int)$ep['ep_no'] ?> · <?= htmlspecialchars($ep['date_human']) ?><?php if ($ep['duration']): ?> · <?= htmlspecialchars($ep['duration']) ?><?php endif; ?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </section>

  <?php elseif ($is_single && $single): ?>
    <article>
      <header class="single-head">
        <nav class="crumbs" aria-label="Brødkrummesti"><a href="/">Forside</a><span aria-hidden="true">›</span><span>Episode <?= (int)$single['ep_no'] ?></span></nav>
        <div class="single-topics"><?php foreach ($single['topics'] as $topic): ?><span class="single-topic"><?= htmlspecialchars($topic) ?></span><?php endforeach; ?></div>
        <h1><?= htmlspecialchars($single['title']) ?></h1>
        <div class="single-meta">
          <span>Episode <?= (int)$single['ep_no'] ?></span><span><?= htmlspecialchars($single['date_human']) ?></span>
          <?php if ($single['duration']): ?><span><?= htmlspecialchars($single['duration']) ?></span><?php endif; ?>
          <?php if ($single['youtube_views']): ?><span><?= htmlspecialchars(format_views($single['youtube_views'])) ?> YouTube-visninger</span><?php endif; ?>
        </div>
      </header>

      <?php if (!empty($single['youtube_id'])): ?>
      <div class="single-video">
        <button class="video-facade" type="button" data-video-id="<?= htmlspecialchars($single['youtube_id']) ?>" data-video-title="<?= htmlspecialchars($single['title']) ?>" aria-label="Afspil <?= htmlspecialchars($single['title']) ?>">
          <img src="<?= htmlspecialchars($single['youtube_thumbnail']) ?>" srcset="<?= htmlspecialchars(youtube_thumbnail_srcset($single['youtube_id'], $single['youtube_thumbnail'])) ?>" sizes="(max-width: 940px) calc(100vw - 32px), 892px" width="1280" height="720" fetchpriority="high" alt="">
          <span class="play-button" aria-hidden="true">▶</span>
        </button>
      </div>
      <?php elseif (!empty($single['image'])): ?>
      <div class="single-video"><div class="audio-visual"><img src="<?= htmlspecialchars($single['image']) ?>" width="600" height="600" alt="Cover for <?= htmlspecialchars($single['title']) ?>"><span class="audio-only-badge">♪ Denne episode er tilgængelig som lyd</span></div></div>
      <?php endif; ?>

      <div class="single-audio">
        <span class="single-audio-label">Lyt som podcast</span>
        <?php if (!empty($single['audio_url'])): ?><audio controls preload="none"><source src="<?= htmlspecialchars($single['audio_url']) ?>" type="audio/mpeg">Din browser understøtter ikke afspilning.</audio><?php endif; ?>
        <nav class="platform-links" aria-label="Lyt på platforme">
          <?php foreach ($platforms as $name => $url): ?><a class="pill" href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener"><?= htmlspecialchars(strtok($name, ' ')) ?></a><?php endforeach; ?>
        </nav>
      </div>

      <?php $share_link = rtrim($short_url, '/') . '/e/' . (int)$single['ep_no']; ?>
      <div class="share-row"><span>Del episoden:</span><span class="share-url"><?= htmlspecialchars(preg_replace('#^https?://#', '', $share_link)) ?></span><button type="button" class="copy-btn" data-link="<?= htmlspecialchars($share_link) ?>" onclick="copyShareLink(this)">Kopiér link</button></div>

      <div class="episode-content-wrap">
        <div>
          <div class="content-label">Om episoden</div>
          <div class="content">
            <?php
              $has_html = $single['content'] !== '' && $single['content'] !== strip_tags($single['content']);
              if ($has_html) echo sanitize_episode_html($single['content']);
              else echo nl2br(htmlspecialchars(html_entity_decode(trim($single['content']), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            ?>
          </div>
        </div>
        <aside class="episode-aside">
          <strong>Fortsæt samtalen</strong>
          <?php if (!empty($single['youtube_id'])): ?><a href="<?= htmlspecialchars('https://www.youtube.com/watch?v=' . $single['youtube_id']) ?>" target="_blank" rel="noopener">Kommentér på YouTube ↗</a><?php endif; ?>
          <a href="<?= htmlspecialchars($platforms['Spotify']) ?>" target="_blank" rel="noopener">Følg på Spotify ↗</a>
          <a href="<?= htmlspecialchars($platforms['Apple Podcasts']) ?>" target="_blank" rel="noopener">Følg på Apple ↗</a>
        </aside>
      </div>

      <div class="nav-ep">
        <?= $prev_link ? '<a class="nav-btn left" href="' . $prev_link . '">' . htmlspecialchars($prev_label) . '</a>' : '<span class="nav-btn left disabled">Ingen tidligere</span>' ?>
        <?= $next_link ? '<a class="nav-btn right" href="' . $next_link . '">' . htmlspecialchars($next_label) . '</a>' : '<span class="nav-btn right disabled">Ingen næste</span>' ?>
      </div>

      <?php
        $related = [];
        foreach ($episodes as $rep) {
            if ((int)$rep['ep_no'] === (int)$single['ep_no']) continue;
            if (array_intersect($single['topics'], $rep['topics'])) $related[] = $rep;
            if (count($related) >= 4) break;
        }
        if (count($related) < 4) {
            foreach ($episodes as $rep) {
                if ((int)$rep['ep_no'] === (int)$single['ep_no']) continue;
                if (!in_array($rep, $related, true)) $related[] = $rep;
                if (count($related) >= 4) break;
            }
        }
      ?>
      <?php if ($related): ?>
      <section class="related" aria-labelledby="related-title">
        <div class="eyebrow">Mere i samme spor</div><h2 id="related-title">Se også</h2>
        <div class="related-grid">
          <?php foreach ($related as $rep): $rthumb = $rep['youtube_thumbnail'] ?: ($rep['image'] ?: $cover_image); ?>
          <a class="related-item-v2" href="<?= '/episode/' . htmlspecialchars($rep['slug']) ?>">
            <?php if ($rthumb): ?><img class="<?= empty($rep['youtube_thumbnail']) ? 'is-cover' : '' ?>" src="<?= htmlspecialchars($rthumb) ?>"<?php if ($rep['youtube_id']): ?> srcset="<?= htmlspecialchars(youtube_thumbnail_srcset($rep['youtube_id'], $rep['youtube_thumbnail'])) ?>" sizes="116px"<?php endif; ?> loading="lazy" width="320" height="180" alt=""><?php endif; ?>
            <span class="related-copy"><span class="related-no">Episode <?= (int)$rep['ep_no'] ?></span><span class="related-title"><?= htmlspecialchars($rep['title']) ?></span></span>
          </a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
    </article>

  <?php elseif ($is_host && $host_profile): ?>
    <article class="host-profile">
      <nav class="crumbs" aria-label="Brødkrummesti"><a href="/">Forside</a><span aria-hidden="true">›</span><a href="/#om-bo">Om mig</a><span aria-hidden="true">›</span><span><?= htmlspecialchars($host_profile['name']) ?></span></nav>
      <header class="host-hero">
        <div class="host-portrait-frame"><img class="host-portrait" src="<?= htmlspecialchars($host_profile['image']) ?>" width="<?= (int)$host_profile['image_width'] ?>" height="<?= (int)$host_profile['image_height'] ?>" alt="<?= htmlspecialchars($host_profile['name']) ?>, vært på <?= htmlspecialchars($site_name) ?>" fetchpriority="high"></div>
        <div>
          <div class="eyebrow">Jeg står bag <?= htmlspecialchars($site_name) ?></div>
          <h1><?= htmlspecialchars($host_profile['name']) ?></h1>
          <p class="host-role"><?= htmlspecialchars($host_profile['role']) ?></p>
          <p class="host-long-bio"><?= htmlspecialchars($host_profile['long_bio']) ?></p>
          <div class="hero-actions">
            <a class="button button-primary" href="<?= htmlspecialchars($host_profile['url']) ?>" target="_blank" rel="me noopener">Besøg mit website ↗</a>
            <a class="button button-secondary" href="<?= htmlspecialchars($host_profile['linkedin']) ?>" target="_blank" rel="me noopener">Min LinkedIn ↗</a>
            <a class="button button-secondary" href="<?= htmlspecialchars($host_profile['newsletter']) ?>" target="_blank" rel="noopener">Få mit nyhedsbrev</a>
          </div>
        </div>
      </header>

      <div class="host-facts">
        <section aria-labelledby="expertise-title">
          <div class="eyebrow">Det arbejder jeg med</div>
          <h2 id="expertise-title">Mine fokusområder</h2>
          <ul class="expertise-list">
            <?php foreach ($host_profile['knowsAbout'] as $topic): ?><li><?= htmlspecialchars($topic) ?></li><?php endforeach; ?>
          </ul>
        </section>
        <section aria-labelledby="companies-title">
          <div class="eyebrow">Mine virksomheder og projekter</div>
          <h2 id="companies-title">Det er jeg involveret i</h2>
          <ul class="company-list">
            <?php foreach ($host_profile['companies'] as $company): ?><li><a href="<?= htmlspecialchars($company['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($company['name']) ?> <span aria-hidden="true">↗</span></a></li><?php endforeach; ?>
          </ul>
        </section>
      </div>

      <section class="section host-episodes" aria-labelledby="host-episodes-title">
        <div class="section-header"><div><div class="eyebrow">Fra min podcast</div><h2 id="host-episodes-title">Mine seneste tanker</h2><p>Jeg fortæller og redigerer selv alle episoder.</p></div></div>
        <div class="episode-grid">
          <?php foreach (array_slice($episodes, 0, 6) as $ep): $thumb = $ep['youtube_thumbnail'] ?: ($ep['image'] ?: $cover_image); ?>
          <a class="episode-card-v2" href="<?= '/episode/' . htmlspecialchars($ep['slug']) ?>">
            <div class="card-media"><?php if ($thumb): ?><img class="<?= empty($ep['youtube_thumbnail']) ? 'is-cover' : '' ?>" src="<?= htmlspecialchars($thumb) ?>"<?php if ($ep['youtube_id']): ?> srcset="<?= htmlspecialchars(youtube_thumbnail_srcset($ep['youtube_id'], $ep['youtube_thumbnail'])) ?>" sizes="(max-width: 650px) 126px, (max-width: 900px) calc(50vw - 30px), 330px"<?php endif; ?> loading="lazy" width="640" height="360" alt=""><?php endif; ?><?php if ($ep['youtube_id']): ?><span class="video-badge">▶ Video</span><?php endif; ?></div>
            <div class="card-body"><div class="card-topics"><?php foreach (array_slice($ep['topics'], 0, 2) as $topic): ?><span class="topic"><?= htmlspecialchars($topic) ?></span><?php endforeach; ?></div><h3><?= htmlspecialchars($ep['title']) ?></h3><div class="card-meta">Ep. <?= (int)$ep['ep_no'] ?> · <?= htmlspecialchars($ep['date_human']) ?><?php if ($ep['duration']): ?> · <?= htmlspecialchars($ep['duration']) ?><?php endif; ?></div></div>
          </a>
          <?php endforeach; ?>
        </div>
        <p class="all-episodes-link"><a href="/#episoder">Se alle <?= count($episodes) ?> episoder →</a></p>
      </section>
    </article>

  <?php else: ?>
    <div class="http404">
      <div class="oops">404</div><h1>Jeg kan ikke finde siden</h1>
      <p class="joke">Jeg er vist kørt ned ad den forkerte afkørsel.</p>
      <a class="home-btn" href="/">Tilbage til forsiden</a>
      <div class="suggest">Eller prøv en af de nyeste episoder:</div>
      <div class="suggest-list"><?php foreach (array_slice($episodes, 0, 5) as $ep): ?><a href="<?= '/episode/' . htmlspecialchars($ep['slug']) ?>">Ep. <?= (int)$ep['ep_no'] ?>: <?= htmlspecialchars(teaser($ep['title'], 42)) ?></a><?php endforeach; ?></div>
    </div>
  <?php endif; ?>

  <?php if (!$is_404): ?>
    <section class="newsletter-cta" aria-label="Nyhedsbrev">
      <div><div class="cta-title">Mine tanker, før de bliver til episoder.</div><p class="cta-desc">Få mine noter om iværksætteri, software og business direkte i indbakken.</p></div>
      <div class="cta-buttons"><a class="cta-btn" href="<?= htmlspecialchars($hosts[0]['newsletter']) ?>" target="_blank" rel="noopener">Tilmeld dig →</a></div>
    </section>
    <?php if (!$is_host): ?>
    <section class="hosts-bio" id="om-bo" aria-labelledby="hosts-title">
      <div class="about-photo"><img src="<?= htmlspecialchars($hosts[0]['image']) ?>" width="1200" height="1200" loading="lazy" alt="Bo Møller på scenen ved Laravel Live i København"><span class="photo-credit">Billedet er taget af fotografen til Laravel Live i København · august 2026</span></div>
      <div class="about-copy"><div class="eyebrow">Lidt om mig</div><h2 id="hosts-title" class="hosts-heading">Hej, jeg er Bo.</h2>
      <?php foreach ($hosts as $host): ?>
      <article class="host">
        <div class="host-desc"><?= htmlspecialchars($host['long_bio']) ?></div>
        <div class="host-elsewhere">
          <p>Jeg er også vært på <a href="https://saaskøbmænd.dk" target="_blank" rel="noopener">SaaS Købmænd</a>, en populær iværksætterpodcast, der udkommer ugentligt.</p>
          <p>Jeg investerer i virksomheder og ejer blandt andet <a href="https://langsom.com" target="_blank" rel="noopener">Langsom.com</a>. Du kan læse mere om mig og mine projekter på <a href="https://bandeja.org" target="_blank" rel="me noopener">Bandeja.org</a> og <a href="https://wmo.dk" target="_blank" rel="noopener">wmo.dk</a>.</p>
        </div>
        <a class="host-profile-link" href="<?= '/vaert/' . htmlspecialchars($host['slug']) ?>" rel="author">Mere om mig →</a>
      </article>
      <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
    <section class="ai-disclosure" aria-labelledby="ai-disclosure-title">
      <div class="ai-disclosure-label"><span aria-hidden="true">bo@showet:~$</span> disclose --ai</div>
      <div class="ai-disclosure-copy">
        <h2 id="ai-disclosure-title">Jeg bruger AI. Helt åbent.</h2>
        <p>Jeg har brugt AI til at bygge og deploye hele siden og til at skrive alle teksterne. Men intet er sat på autopilot: Jeg, Bo Møller, har orkestreret det hele, valgt retningen og godkendt resultatet.</p>
        <p>Alle videoer er mig, der taler - ikke AI. Endnu :-)</p>
      </div>
      <div class="ai-disclosure-status" aria-label="Menneske i processen"><span aria-hidden="true">●</span> human_in_the_loop = true</div>
    </section>
  <?php endif; ?>
  </main>
  <footer class="site-footer">
    <div><strong><?= htmlspecialchars($site_name) ?></strong><span>Mine tanker på farten om business, software og livet.</span></div>
    <nav aria-label="Praktiske links"><?php foreach ($platforms as $name => $url): ?><a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($name) ?></a><?php endforeach; ?><a href="<?= htmlspecialchars($public_rss_url) ?>">RSS-feed</a><a href="/sitemap.xml">Sitemap</a><a href="/llms.txt">llms.txt</a></nav>
  </footer>
</div>


<script>
// Kopiér del-link. Bruger Clipboard API i secure context (HTTPS), ellers
// en execCommand-fallback, så knappen også virker uden HTTPS.
function copyShareLink(btn){
  var link = btn.getAttribute('data-link');
  function done(){
    var t = btn.textContent;
    btn.textContent = 'Kopieret ✓';
    setTimeout(function(){ btn.textContent = t; }, 1500);
  }
  function fallback(){
    var ta = document.createElement('textarea');
    ta.value = link; ta.setAttribute('readonly','');
    ta.style.position = 'fixed'; ta.style.left = '-9999px';
    document.body.appendChild(ta);
    ta.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch(e){}
    document.body.removeChild(ta);
    if (ok) done();
  }
  if (navigator.clipboard && navigator.clipboard.writeText){
    navigator.clipboard.writeText(link).then(done, fallback);
  } else {
    fallback();
  }
}
</script>
<script src="/assets/site-v2.js?v=1"></script>
</body>
</html>
