<?php

namespace Cable8mm\Youtube\Tests\Cache;

use Cable8mm\Youtube\Cache\YoutubeCache;
use Illuminate\Contracts\Cache\Repository;
use Mockery;
use PHPUnit\Framework\TestCase;

class YoutubeCacheTest extends TestCase
{
    private YoutubeCache $cache;

    protected function setUp(): void
    {
        $mockRepository = Mockery::mock(Repository::class);
        $this->cache = new YoutubeCache($mockRepository, 3600);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    // ==================== Constructor Tests ====================

    public function test_constructor_sets_default_ttl(): void
    {
        $mockRepository = Mockery::mock(Repository::class);
        $cache = new YoutubeCache($mockRepository);

        $reflection = new \ReflectionClass($cache);
        $property = $reflection->getProperty('ttl');
        $property->setAccessible(true);

        $this->assertEquals(3600, $property->getValue($cache));
    }

    public function test_constructor_sets_custom_ttl(): void
    {
        $mockRepository = Mockery::mock(Repository::class);
        $cache = new YoutubeCache($mockRepository, 1800);

        $reflection = new \ReflectionClass($cache);
        $property = $reflection->getProperty('ttl');
        $property->setAccessible(true);

        $this->assertEquals(1800, $property->getValue($cache));
    }

    // ==================== Remember Method Tests ====================

    public function test_remember_returns_cached_value(): void
    {
        $mockRepository = Mockery::mock(Repository::class);
        $mockRepository->shouldReceive('remember')
            ->once()
            ->with('test_key', 3600, Mockery::type('callable'))
            ->andReturn('cached_value');

        $cache = new YoutubeCache($mockRepository);
        $result = $cache->remember('test_key', function () {
            return 'new_value';
        });

        $this->assertEquals('cached_value', $result);
    }

    public function test_remember_executes_callback_when_not_cached(): void
    {
        $mockRepository = Mockery::mock(Repository::class);
        $mockRepository->shouldReceive('remember')
            ->once()
            ->with('test_key', 3600, Mockery::type('callable'))
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $cache = new YoutubeCache($mockRepository);
        $result = $cache->remember('test_key', function () {
            return 'fresh_value';
        });

        $this->assertEquals('fresh_value', $result);
    }

    // ==================== Get Method Tests ====================

    public function test_get_returns_cached_value(): void
    {
        $mockRepository = Mockery::mock(Repository::class);
        $mockRepository->shouldReceive('get')
            ->once()
            ->with('test_key')
            ->andReturn('cached_data');

        $cache = new YoutubeCache($mockRepository);
        $result = $cache->get('test_key');

        $this->assertEquals('cached_data', $result);
    }

    public function test_get_returns_null_when_not_cached(): void
    {
        $mockRepository = Mockery::mock(Repository::class);
        $mockRepository->shouldReceive('get')
            ->once()
            ->with('nonexistent_key')
            ->andReturn(null);

        $cache = new YoutubeCache($mockRepository);
        $result = $cache->get('nonexistent_key');

        $this->assertNull($result);
    }

    // ==================== Put Method Tests ====================

    public function test_put_stores_value_in_cache(): void
    {
        $mockRepository = Mockery::mock(Repository::class);
        $mockRepository->shouldReceive('put')
            ->once()
            ->with('test_key', 'test_value', 3600)
            ->andReturn(true);

        $cache = new YoutubeCache($mockRepository);
        $result = $cache->put('test_key', 'test_value');

        $this->assertTrue($result);
    }

    public function test_put_uses_custom_ttl(): void
    {
        $mockRepository = Mockery::mock(Repository::class);
        $mockRepository->shouldReceive('put')
            ->once()
            ->with('test_key', 'test_value', 1800)
            ->andReturn(true);

        $cache = new YoutubeCache($mockRepository, 1800);
        $result = $cache->put('test_key', 'test_value');

        $this->assertTrue($result);
    }

    // ==================== Has Method Tests ====================

    public function test_has_returns_true_when_key_exists(): void
    {
        $mockRepository = Mockery::mock(Repository::class);
        $mockRepository->shouldReceive('has')
            ->once()
            ->with('existing_key')
            ->andReturn(true);

        $cache = new YoutubeCache($mockRepository);
        $result = $cache->has('existing_key');

        $this->assertTrue($result);
    }

    public function test_has_returns_false_when_key_does_not_exist(): void
    {
        $mockRepository = Mockery::mock(Repository::class);
        $mockRepository->shouldReceive('has')
            ->once()
            ->with('nonexistent_key')
            ->andReturn(false);

        $cache = new YoutubeCache($mockRepository);
        $result = $cache->has('nonexistent_key');

        $this->assertFalse($result);
    }

    // ==================== Forget Method Tests ====================

    public function test_forget_removes_key_from_cache(): void
    {
        $mockRepository = Mockery::mock(Repository::class);
        $mockRepository->shouldReceive('forget')
            ->once()
            ->with('test_key')
            ->andReturn(true);

        $cache = new YoutubeCache($mockRepository);
        $result = $cache->forget('test_key');

        $this->assertTrue($result);
    }

    // ==================== Clear Method Tests ====================

    public function test_clear_removes_all_cache(): void
    {
        $mockRepository = Mockery::mock(Repository::class);
        $mockRepository->shouldReceive('clear')
            ->once()
            ->andReturn(true);

        $cache = new YoutubeCache($mockRepository);
        $result = $cache->clear();

        $this->assertTrue($result);
    }

    // ==================== SetTtl Method Tests ====================

    public function test_set_ttl_updates_ttl_and_returns_self(): void
    {
        $cache = new YoutubeCache(Mockery::mock(Repository::class));
        $result = $cache->setTtl(7200);

        $this->assertInstanceOf(YoutubeCache::class, $result);

        $reflection = new \ReflectionClass($cache);
        $property = $reflection->getProperty('ttl');
        $property->setAccessible(true);

        $this->assertEquals(7200, $property->getValue($cache));
    }

    // ==================== GenerateKey Method Tests ====================

    public function test_generate_key_creates_consistent_keys(): void
    {
        $url = 'https://www.googleapis.com/youtube/v3/videos';
        $params = ['part' => 'snippet', 'id' => 'test123'];

        $key1 = $this->cache->generateKey($url, $params);
        $key2 = $this->cache->generateKey($url, $params);

        $this->assertEquals($key1, $key2);
    }

    public function test_generate_key_creates_different_keys_for_different_params(): void
    {
        $url = 'https://www.googleapis.com/youtube/v3/videos';
        $params1 = ['part' => 'snippet', 'id' => 'test123'];
        $params2 = ['part' => 'snippet', 'id' => 'test456'];

        $key1 = $this->cache->generateKey($url, $params1);
        $key2 = $this->cache->generateKey($url, $params2);

        $this->assertNotEquals($key1, $key2);
    }

    public function test_generate_key_creates_different_keys_for_different_urls(): void
    {
        $params = ['part' => 'snippet'];
        $url1 = 'https://www.googleapis.com/youtube/v3/videos';
        $url2 = 'https://www.googleapis.com/youtube/v3/search';

        $key1 = $this->cache->generateKey($url1, $params);
        $key2 = $this->cache->generateKey($url2, $params);

        $this->assertNotEquals($key1, $key2);
    }

    public function test_generate_key_has_youtube_api_prefix(): void
    {
        $url = 'https://www.googleapis.com/youtube/v3/videos';
        $params = ['part' => 'snippet'];

        $key = $this->cache->generateKey($url, $params);

        $this->assertStringStartsWith('youtube_api_', $key);
    }

    public function test_generate_key_sorts_params_before_hashing(): void
    {
        $url = 'https://www.googleapis.com/youtube/v3/videos';
        $params1 = ['part' => 'snippet', 'id' => 'test123'];
        $params2 = ['id' => 'test123', 'part' => 'snippet']; // Different order

        $key1 = $this->cache->generateKey($url, $params1);
        $key2 = $this->cache->generateKey($url, $params2);

        $this->assertEquals($key1, $key2);
    }

    // ==================== Fluent Interface Tests ====================

    public function test_set_ttl_returns_self_for_fluent_interface(): void
    {
        $cache = new YoutubeCache(Mockery::mock(Repository::class));
        $result = $cache->setTtl(1800);

        $this->assertSame($cache, $result);
    }
}
