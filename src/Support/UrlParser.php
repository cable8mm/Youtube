<?php

namespace Cable8mm\Youtube\Support;

class UrlParser
{
    /**
     * Paths on youtube.com that are not channel pages.
     */
    public const RESERVED_PATHS = [
        '\/about\b',
        '\/account\b',
        '\/account_(.*)',
        '\/ads\b',
        '\/creators\b',
        '\/feed\b',
        '\/feed\/(.*)',
        '\/gaming\b',
        '\/gaming\/(.*)',
        '\/howyoutubeworks\b',
        '\/howyoutubeworks\/(.*)',
        '\/new\b',
        '\/playlist\b',
        '\/playlist\/(.*)',
        '\/reporthistory',
        '\/results\b',
        '\/shorts\b',
        '\/shorts\/(.*)',
        '\/t\/(.*)',
        '\/upload\b',
        '\/yt\/(.*)',
    ];

    /**
     * Hosts whose URLs carry a Video ID in `v` or after `/embed/`.
     */
    public const VIDEO_HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'music.youtube.com',
        'youtube-nocookie.com',
        'www.youtube-nocookie.com',
    ];

    /**
     * Parse a youtube URL to get the youtube Vid.
     * Support full URLs (www.youtube.com), embed URLs, and short URLs (youtu.be)
     *
     * @throws \InvalidArgumentException
     */
    public static function videoId(string $youtube_url): string
    {
        $url = static::withScheme($youtube_url);
        $host = static::host($url);
        $segments = static::segments($url);
        $vid = null;

        if ($host === 'youtu.be') {
            $vid = $segments[0] ?? null;
        } elseif (in_array($host, self::VIDEO_HOSTS, true)) {
            $vid = ($segments[0] ?? null) === 'embed'
                ? $segments[1] ?? null
                : static::query($url)['v'] ?? null;
        }

        if (! is_string($vid) || $vid === '') {
            throw new \InvalidArgumentException('The supplied URL does not look like a Youtube URL');
        }

        return $vid;
    }

    /**
     * Work out how to look up the channel behind a channel page URL.
     *
     * @return array{type: 'id'|'username'|'search', value: string}
     *
     * @throws \InvalidArgumentException
     */
    public static function channel(string $youtube_url): array
    {
        if (strpos($youtube_url, 'youtube.com') === false) {
            throw new \InvalidArgumentException('The supplied URL does not look like a Youtube URL');
        }

        $path = static::path($youtube_url);
        $segments = explode('/', $path);
        $last = $segments[count($segments) - 1];

        if (strpos($path, '/channel/') === 0) {
            return ['type' => 'id', 'value' => $last];
        }

        if (strpos($path, '/user/') === 0) {
            return ['type' => 'username', 'value' => $last];
        }

        if (strpos($path, '/c/') === 0) {
            return ['type' => 'search', 'value' => $last];
        }

        if (strpos($path, '/@') === 0) {
            return ['type' => 'search', 'value' => str_replace('@', '', $last)];
        }

        foreach (self::RESERVED_PATHS as $r) {
            if (preg_match('/'.$r.'/', $path)) {
                throw new \InvalidArgumentException('The supplied URL does not look like a Youtube Channel URL');
            }
        }

        if (empty($segments[1])) {
            throw new \InvalidArgumentException('The supplied URL does not look like a Youtube Channel URL');
        }

        return ['type' => 'search', 'value' => $segments[1]];
    }

    /**
     * Prefix https:// to a URL that has no scheme, so parse_url finds its host
     */
    public static function withScheme(string $url): string
    {
        return str_contains($url, '://') ? $url : 'https://'.$url;
    }

    /**
     * Parse the input url string and return its lowercased host
     */
    public static function host(string $url): string
    {
        return strtolower(parse_url($url, PHP_URL_HOST) ?: '');
    }

    /**
     * Parse the input url string and return the non-empty segments of its path
     *
     * @return string[]
     */
    public static function segments(string $url): array
    {
        return array_values(array_filter(explode('/', static::path($url)), fn ($segment) => $segment !== ''));
    }

    /**
     * Parse the input url string and return just the path part
     */
    public static function path(string $url): string
    {
        return parse_url($url, PHP_URL_PATH) ?: '';
    }

    /**
     * Parse the input url string and return an array of query params
     */
    public static function query(string $url): array
    {
        parse_str(parse_url($url, PHP_URL_QUERY) ?: '', $params);

        return $params;
    }
}
