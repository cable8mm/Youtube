<?php

namespace Cable8mm\Youtube\Http;

use Cable8mm\Youtube\Cache\YoutubeCache;
use Cable8mm\Youtube\Exceptions\YoutubeApiException;

class ApiClient
{
    /**
     * YouTube Data API v3 endpoints.
     */
    public const ENDPOINTS = [
        'categories.list' => 'https://www.googleapis.com/youtube/v3/videoCategories',
        'videos.list' => 'https://www.googleapis.com/youtube/v3/videos',
        'search.list' => 'https://www.googleapis.com/youtube/v3/search',
        'channels.list' => 'https://www.googleapis.com/youtube/v3/channels',
        'playlists.list' => 'https://www.googleapis.com/youtube/v3/playlists',
        'playlistItems.list' => 'https://www.googleapis.com/youtube/v3/playlistItems',
        'activities' => 'https://www.googleapis.com/youtube/v3/activities',
        'commentThreads.list' => 'https://www.googleapis.com/youtube/v3/commentThreads',
    ];

    public function __construct(
        protected string $apiKey,
        protected bool $useHttpHost = false,
        protected bool $cacheEnabled = false,
        protected int $cacheTtl = 3600,
    ) {}

    public function setApiKey(string $key): self
    {
        $this->apiKey = $key;

        return $this;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function useHttpHost(bool $setting): self
    {
        $this->useHttpHost = $setting;

        return $this;
    }

    public function isUsingHttpHost(): bool
    {
        return $this->useHttpHost;
    }

    /**
     * Enable or disable caching.
     */
    public function cache(bool $enabled = true): self
    {
        $this->cacheEnabled = $enabled;

        return $this;
    }

    public function isCacheEnabled(): bool
    {
        return $this->cacheEnabled;
    }

    /**
     * Set cache TTL.
     */
    public function setCacheTtl(int $ttl): self
    {
        $this->cacheTtl = $ttl;

        return $this;
    }

    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    /**
     * Get the URL of a named endpoint.
     */
    public function endpoint(string $name): string
    {
        return self::ENDPOINTS[$name];
    }

    /**
     * Issue a GET request to a named endpoint.
     *
     * @throws YoutubeApiException
     */
    public function call(string $name, array $params): string
    {
        return $this->get($this->endpoint($name), $params);
    }

    /**
     * Issue a GET request, serving from cache when enabled.
     *
     * @throws YoutubeApiException
     */
    public function get(string $url, array $params): string
    {
        $params['key'] = $this->apiKey;

        $cache = $this->resolveCache();

        if ($cache !== null) {
            $cacheKey = $cache->generateKey($url, $params);
            $cached = $cache->get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        $response = $this->send($url, $params);

        if ($cache !== null && $this->isCacheable($response)) {
            $cache->put($cacheKey, $response);
        }

        return $response;
    }

    /**
     * Only successful JSON responses are cached, so errors such as an
     * exceeded quota are not replayed for the whole TTL.
     */
    protected function isCacheable(string $response): bool
    {
        $decoded = json_decode($response);

        return is_object($decoded) && ! isset($decoded->error);
    }

    /**
     * Using CURL to issue a GET request.
     *
     * @throws YoutubeApiException
     */
    protected function send(string $url, array $params): string
    {
        $curl = curl_init();

        if ($this->useHttpHost && isset($_SERVER['HTTP_HOST'])) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Referer: '.$_SERVER['HTTP_HOST'],
            ]);
        }

        curl_setopt($curl, CURLOPT_URL, $url.(strpos($url, '?') === false ? '?' : '').http_build_query($params));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            throw new YoutubeApiException('Curl Error : '.curl_error($curl));
        }

        return $response;
    }

    /**
     * Resolve the Laravel cache store, or null when caching is unavailable.
     */
    protected function resolveCache(): ?YoutubeCache
    {
        if (! $this->cacheEnabled || ! function_exists('app') || ! app()->bound('cache')) {
            return null;
        }

        return new YoutubeCache(app('cache')->store(), $this->cacheTtl);
    }
}
