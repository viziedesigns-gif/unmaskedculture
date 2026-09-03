<?php
/**
 * Podbean RSS reader for the in-app Kinto podcast library.
 */

const KINTO_PODCAST_FEED_URL = 'https://feed.podbean.com/umaskedculture/feed.xml';
const KINTO_PODCAST_CACHE_TTL = 900;

/** @return array{title:string,description:string,image:string,link:string,episodes:array<int,array<string,mixed>>,updated_at:string} */
function getKintoPodcastFeed(): array {
    $cacheFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'kinto-podcast-feed-v1.json';
    $cached = readKintoPodcastCache($cacheFile);
    if ($cached !== null && (time() - (int) ($cached['_cached_at'] ?? 0)) < KINTO_PODCAST_CACHE_TTL) {
        unset($cached['_cached_at']);
        return $cached;
    }

    $xml = fetchKintoPodcastXml(KINTO_PODCAST_FEED_URL);
    if ($xml !== null) {
        $parsed = parseKintoPodcastXml($xml);
        if ($parsed !== null) {
            $cacheValue = $parsed;
            $cacheValue['_cached_at'] = time();
            @file_put_contents($cacheFile, json_encode($cacheValue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
            return $parsed;
        }
    }

    if ($cached !== null) {
        unset($cached['_cached_at']);
        return $cached;
    }

    return [
        'title' => 'The Unmasked Podcast',
        'description' => 'Conversations around mental health.',
        'image' => '',
        'link' => 'https://umaskedculture.podbean.com',
        'episodes' => [],
        'updated_at' => '',
    ];
}

function readKintoPodcastCache(string $cacheFile): ?array {
    if (!is_file($cacheFile)) return null;
    $contents = @file_get_contents($cacheFile);
    if (!is_string($contents) || $contents === '') return null;
    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : null;
}

function fetchKintoPodcastXml(string $url): ?string {
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_USERAGENT => 'Kinto Podcast Player/1.0',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if (is_string($body) && $body !== '' && $status >= 200 && $status < 300) return $body;
    }

    $context = stream_context_create(['http' => [
        'timeout' => 12,
        'follow_location' => 1,
        'user_agent' => 'Kinto Podcast Player/1.0',
    ]]);
    $body = @file_get_contents($url, false, $context);
    return is_string($body) && $body !== '' ? $body : null;
}

/** @return array{title:string,description:string,image:string,link:string,episodes:array<int,array<string,mixed>>,updated_at:string}|null */
function parseKintoPodcastXml(string $xml): ?array {
    $previous = libxml_use_internal_errors(true);
    $rss = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$rss || !isset($rss->channel)) return null;

    $channel = $rss->channel;
    $namespaces = $rss->getNamespaces(true);
    $itunesNamespace = $namespaces['itunes'] ?? 'http://www.itunes.com/dtds/podcast-1.0.dtd';
    $channelItunes = $channel->children($itunesNamespace);
    $channelImage = trim((string) ($channel->image->url ?? ''));
    if ($channelImage === '' && isset($channelItunes->image)) {
        $channelImage = trim((string) $channelItunes->image->attributes()->href);
    }

    $episodes = [];
    foreach ($channel->item as $item) {
        $enclosure = $item->enclosure;
        $audioUrl = trim((string) $enclosure['url']);
        if (!isSafePodcastUrl($audioUrl)) continue;

        $itemItunes = $item->children($itunesNamespace);
        $episodeImage = '';
        if (isset($itemItunes->image)) {
            $episodeImage = trim((string) $itemItunes->image->attributes()->href);
        }
        if (!isSafePodcastUrl($episodeImage)) $episodeImage = $channelImage;

        $publishedAt = '';
        $publishedLabel = '';
        try {
            $published = new DateTimeImmutable((string) $item->pubDate);
            $publishedAt = $published->format(DateTimeInterface::ATOM);
            $publishedLabel = $published->format('M j, Y');
        } catch (Exception $e) {
            // Leave malformed dates blank instead of hiding the episode.
        }

        $description = html_entity_decode(strip_tags((string) $item->description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = trim((string) preg_replace('/\s+/u', ' ', $description));
        $guid = trim((string) $item->guid);
        $duration = trim((string) ($itemItunes->duration ?? ''));

        $episodes[] = [
            'id' => substr(hash('sha256', $guid !== '' ? $guid : $audioUrl), 0, 20),
            'title' => trim((string) $item->title),
            'description' => $description,
            'audio_url' => $audioUrl,
            'image' => $episodeImage,
            'link' => isSafePodcastUrl(trim((string) $item->link)) ? trim((string) $item->link) : '',
            'published_at' => $publishedAt,
            'published_label' => $publishedLabel,
            'duration' => $duration,
        ];
    }

    return [
        'title' => trim((string) $channel->title) ?: 'The Unmasked Podcast',
        'description' => trim((string) $channel->description),
        'image' => isSafePodcastUrl($channelImage) ? $channelImage : '',
        'link' => isSafePodcastUrl(trim((string) $channel->link)) ? trim((string) $channel->link) : 'https://umaskedculture.podbean.com',
        'episodes' => $episodes,
        'updated_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
    ];
}

function isSafePodcastUrl(string $url): bool {
    if ($url === '') return false;
    $parts = parse_url($url);
    return is_array($parts) && strtolower((string) ($parts['scheme'] ?? '')) === 'https' && !empty($parts['host']);
}
