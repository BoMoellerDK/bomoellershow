<?php
$base_url = getenv('BOMOELLERSHOW_TEST_BASE_URL') ?: 'http://127.0.0.1:8878';
$runtime_file = getenv('BOMOELLERSHOW_YOUTUBE_RUNTIME_FILE');
$checks = 0;
$failures = [];

function synthetic_check($condition, $message) {
    global $checks, $failures;
    $checks++;
    if (!$condition) $failures[] = $message;
}

function synthetic_request($path) {
    global $base_url;
    $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]);
    $body = @file_get_contents($base_url . $path, false, $context);
    $headers = isset($http_response_header) ? $http_response_header : [];
    $status = 0;
    if (isset($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $match)) $status = (int)$match[1];
    return ['status' => $status, 'body' => $body === false ? '' : $body];
}

function synthetic_meta($html, $property) {
    if ($html === '') return '';
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//meta[@property="' . $property . '"]/@content');
    return $nodes->length ? $nodes->item(0)->nodeValue : '';
}

$home = synthetic_request('/');
synthetic_check($home['status'] === 200, 'Den syntetiske forside svarer ikke 200');
synthetic_check(substr_count($home['body'], 'class="episode-card-v2"') === 2, 'Den syntetiske RSS-fixture giver ikke to episoder');
synthetic_check(strpos($home['body'], '/vi/AbCdEfGhI_1/') !== false, 'Episode 5 blev ikke matchet med Atom-feedet');

$episode_73_path = '';
if (preg_match('#href="(/episode/5-[^"]+)"#', $home['body'], $match)) $episode_73_path = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
synthetic_check($episode_73_path !== '', 'Episode 5 mangler en episodeside');

$episode_73 = synthetic_request($episode_73_path);
synthetic_check($episode_73['status'] === 200, 'Episode 5 svarer ikke 200');
synthetic_check(synthetic_meta($episode_73['body'], 'og:title') === 'Episode 5: Automatiseret testepisode om video – Bo Møller showet', 'Episode 5 har forkert OG-titel');
synthetic_check(synthetic_meta($episode_73['body'], 'og:image') === 'https://i.ytimg.com/vi/AbCdEfGhI_1/hqdefault.jpg', 'Episode 5 bruger ikke Atom-feedets eksisterende thumbnail');
synthetic_check(synthetic_meta($episode_73['body'], 'og:image:width') === '480' && synthetic_meta($episode_73['body'], 'og:image:height') === '360', 'Episode 5 har forkerte OG-dimensioner');
synthetic_check(strpos($episode_73['body'], 'VideoObject') !== false, 'Episode 5 mangler VideoObject-schema');
synthetic_check(strpos($episode_73['body'], 'alert(1)') === false, 'RSS-HTML beholder eksekverbar kode fra feedet');
synthetic_check(strpos($episode_73['body'], 'javascript:alert') === false, 'RSS-HTML beholder et usikkert link');

$episode_72_path = '';
if (preg_match('#href="(/episode/4-[^"]+)"#', $home['body'], $match)) $episode_72_path = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
synthetic_check($episode_72_path !== '', 'Den kuraterede episode 4 mangler en episodeside');
$episode_72 = synthetic_request($episode_72_path);
synthetic_check($episode_72['status'] === 200, 'Den kuraterede episode 4 svarer ikke 200');
synthetic_check(synthetic_meta($episode_72['body'], 'og:image') === 'https://i.ytimg.com/vi/EsDzRsIDTBU/hqdefault.jpg', 'Atom-feedet overskriver seed-thumbnailen for episode 4');
synthetic_check(synthetic_meta($episode_72['body'], 'og:image:width') === '480' && synthetic_meta($episode_72['body'], 'og:image:height') === '360', 'Episode 4 mistede seedets dimensioner');

synthetic_check($runtime_file && is_file($runtime_file), 'Det syntetiske runtime-katalog blev ikke gemt');
$runtime_before = $runtime_file && is_file($runtime_file) ? file_get_contents($runtime_file) : '';
$runtime_payload = json_decode($runtime_before, true);
$runtime_episodes = is_array($runtime_payload) && isset($runtime_payload['episodes']) ? $runtime_payload['episodes'] : [];
synthetic_check(array_keys($runtime_episodes) === [5], 'Runtime-kataloget indeholder andet end den nye episode 5');
synthetic_check(($runtime_episodes[5]['id'] ?? '') === 'AbCdEfGhI_1', 'Runtime-kataloget gemte forkert video for episode 5');
synthetic_check(($runtime_episodes[5]['thumbnail'] ?? '') === 'https://i.ytimg.com/vi/AbCdEfGhI_1/hqdefault.jpg', 'Runtime-kataloget gemte forkert thumbnail for episode 5');

usleep(1100000);
synthetic_request('/');
$runtime_after = $runtime_file && is_file($runtime_file) ? file_get_contents($runtime_file) : '';
synthetic_check($runtime_before === $runtime_after, 'Det syntetiske runtime-katalog omskrives uden ændringer');
if ($runtime_file && is_file($runtime_file)) @unlink($runtime_file);

echo json_encode([
    'checks' => $checks,
    'failures' => $failures,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

exit($failures ? 1 : 0);
