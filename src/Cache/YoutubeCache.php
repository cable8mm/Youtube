<?php

namespace Cable8mm\Youtube\Cache;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

class YoutubeCache
{
    protected CacheRepository $cache;

    protected int $ttl;

    /**
     * Create a new YouTube cache instance.
     */
    public function __construct(CacheRepository $cache, int $ttl = 3600)
    {
        $this->cache = $cache;
        $this->ttl = $ttl;
    }

    /**
     * Get cached response or execute callback and cache result.
     *
     * @return mixed
     */
    public function remember(string $key, callable $callback)
    {
        return $this->cache->remember($key, $this->ttl, $callback);
    }

    /**
     * Get cached response.
     */
    public function get(string $key): mixed
    {
        return $this->cache->get($key);
    }

    /**
     * Store response in cache.
     */
    public function put(string $key, mixed $value): bool
    {
        return $this->cache->put($key, $value, $this->ttl);
    }

    /**
     * Check if cache has key.
     */
    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }

    /**
     * Remove item from cache.
     */
    public function forget(string $key): bool
    {
        return $this->cache->forget($key);
    }

    /**
     * Clear all cache.
     */
    public function clear(): bool
    {
        return $this->cache->clear();
    }

    /**
     * Set cache TTL.
     */
    public function setTtl(int $ttl): self
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * Generate cache key from URL and params.
     */
    public function generateKey(string $url, array $params): string
    {
        ksort($params);

        return 'youtube_api_'.md5($url.http_build_query($params));
    }
}
