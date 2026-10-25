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
     * Parse a youtube URL to get the youtube Vid.
     * Support both full URL (www.youtube.com) and short URL (youtu.be)
     *
     * @throws \InvalidArgumentException
     */
    public static function videoId(string $youtube_url): string
    {
        if (strpos($youtube_url, 'youtube.com') !== false) {
            if (strpos($youtube_url, 'embed') !== false) {
                return substr(static::path($youtube_url), 7);
            }

            $params = static::query($youtube_url);

            if (! isset($params['v'])) {
                throw new \InvalidArgumentException('The supplied URL does not look like a Youtube URL');
            }

            return $params['v'];
        }

        if (strpos($youtube_url, 'youtu.be') !== false) {
            return substr(static::path($youtube_url), 1);
        }

        throw new \InvalidArgumentException('The supplied URL does not look like a Youtube URL');
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

        return ['type' => 'search', 'value' => $segments[1]];
    }

    /**
     * Parse the input url string and return just the path part
     */
    public static function path(string $url): string
    {
        $array = parse_url($url);

        return $array['path'];
    }

    /**
     * Parse the input url string and return an array of query params
     */
    public static function query(string $url): array
    {
        $array = parse_url($url);
        $query = $array['query'];

        $queryParts = explode('&', $query);

        $params = [];
        foreach ($queryParts as $param) {
            $item = explode('=', $param);
            $params[$item[0]] = empty($item[1]) ? '' : $item[1];
        }

        return $params;
    }
}
