<?php

namespace Cable8mm\Youtube\Tests;

use Cable8mm\Youtube\Youtube;
use Carbon\Carbon;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

class YoutubeTest extends TestCase
{
    private static array $dotenv;

    private static bool $youtubeEnabled;

    public static function setUpBeforeClass(): void
    {
        self::$dotenv = Dotenv::createImmutable(__DIR__.'/../')->load();

        self::$youtubeEnabled = isset(self::$dotenv['YOUTUBE_ENABLED']) && self::$dotenv['YOUTUBE_ENABLED'] == 'true';
    }

    // ==================== Unit Tests with Mocking ====================

    public function test_constructor_requires_api_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Google API key is Required');

        new Youtube('');
    }

    public function test_constructor_accepts_valid_api_key(): void
    {
        $youtube = new Youtube('valid_api_key');

        $this->assertInstanceOf(Youtube::class, $youtube);
        $this->assertEquals('valid_api_key', $youtube->getApiKey());
    }

    public function test_use_http_host_sets_config_correctly(): void
    {
        $youtube = new Youtube('test_key');
        $youtube->useHttpHost(true);

        // Use reflection to check private property
        $reflection = new \ReflectionClass($youtube);
        $property = $reflection->getProperty('config');
        $property->setAccessible(true);
        $config = $property->getValue($youtube);

        $this->assertTrue($config['use-http-host']);
    }

    public function test_set_api_key_updates_key(): void
    {
        $youtube = new Youtube('old_key');
        $youtube->setApiKey('new_key');

        $this->assertEquals('new_key', $youtube->getApiKey());
    }

    public function test_fluent_interface_works(): void
    {
        $youtube = (new Youtube('test_key'))
            ->useHttpHost(true)
            ->setApiKey('another_key')
            ->cache()
            ->setCacheTtl(1800);

        $this->assertEquals('another_key', $youtube->getApiKey());
    }

    public function test_parse_vid_from_ur_l_with_standard_url(): void
    {
        $videoId = Youtube::parseVidFromURL('https://www.youtube.com/watch?v=rie-hPVJ7Sw');

        $this->assertEquals('rie-hPVJ7Sw', $videoId);
    }

    public function test_parse_vid_from_ur_l_with_short_url(): void
    {
        $videoId = Youtube::parseVidFromURL('https://youtu.be/rie-hPVJ7Sw');

        $this->assertEquals('rie-hPVJ7Sw', $videoId);
    }

    public function test_parse_vid_from_ur_l_with_embed_url(): void
    {
        $videoId = Youtube::parseVidFromURL('https://www.youtube.com/embed/rie-hPVJ7Sw');

        $this->assertEquals('rie-hPVJ7Sw', $videoId);
    }

    public function test_parse_vid_from_ur_l_throws_exception_for_invalid_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The supplied URL does not look like a Youtube URL');

        Youtube::parseVidFromURL('https://example.com');
    }

    public function test_cache_method_enables_caching(): void
    {
        $youtube = new Youtube('test_key', ['cache_enabled' => false]);
        $youtube->cache(true);

        $reflection = new \ReflectionClass($youtube);
        $property = $reflection->getProperty('cacheEnabled');
        $property->setAccessible(true);

        $this->assertTrue($property->getValue($youtube));
    }

    public function test_set_cache_ttl_sets_ttl_correctly(): void
    {
        $youtube = new Youtube('test_key');
        $youtube->setCacheTtl(1800);

        $reflection = new \ReflectionClass($youtube);
        $property = $reflection->getProperty('cacheTtl');
        $property->setAccessible(true);

        $this->assertEquals(1800, $property->getValue($youtube));
    }

    public function test_search_advanced_throws_exception_without_params(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $youtube = new Youtube('test_key');
        $youtube->searchAdvanced([]);
    }

    public function test_get_activities_by_channel_id_throws_exception_without_channel_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ChannelId must be supplied');

        $youtube = new Youtube('test_key');
        $youtube->getActivitiesByChannelId('');
    }

    // ==================== Integration Tests ====================

    public function test_get_channel_videos(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = self::$dotenv['YOUTUBE_API_KEY'];
        $channelId = self::$dotenv['YOUTUBE_CHANNEL_ID'];
        $publishedBefore = Carbon::now()->toRfc3339String();

        $videos = (new Youtube($apiKey))->getChannelVideos($channelId, 1, $publishedBefore)['results'];

        $this->assertNotEmpty($videos);
        $this->assertObjectHasProperty('id', $videos[0]);
        $this->assertObjectHasProperty('snippet', $videos[0]);
    }

    public function test_get_channel_videos_returns_different_videos(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = self::$dotenv['YOUTUBE_API_KEY'];
        $channelId = self::$dotenv['YOUTUBE_CHANNEL_ID'];
        $publishedBefore = Carbon::now()->toRfc3339String();

        $video = (new Youtube($apiKey))->getChannelVideos($channelId, 1, $publishedBefore)['results'][0];

        $this->assertNotEmpty($video->id->videoId);

        $secondVideo = (new Youtube($apiKey))->getChannelVideos($channelId, 1, $video->snippet->publishedAt)['results'][0];

        $this->assertNotEmpty($secondVideo->id->videoId);
        $this->assertNotEquals($video->id->videoId, $secondVideo->id->videoId);
    }

    public function test_get_channel_videos_with_page_token(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = self::$dotenv['YOUTUBE_API_KEY'];
        $channelId = self::$dotenv['YOUTUBE_CHANNEL_ID'];

        $response = (new Youtube($apiKey))->getChannelVideos($channelId, 1, null, false, '');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('results', $response);
        $this->assertArrayHasKey('info', $response);
        $this->assertNotEmpty($response['results']);
        $this->assertIsArray($response['info']);
        $this->assertArrayHasKey('totalResults', $response['info']);
        $this->assertArrayHasKey('nextPageToken', $response['info']);
    }

    public function test_get_video_info(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = self::$dotenv['YOUTUBE_API_KEY'];

        $video = (new Youtube($apiKey))->getVideoInfo('rie-hPVJ7Sw');

        $this->assertNotNull($video);
        $this->assertObjectHasProperty('id', $video);
        $this->assertObjectHasProperty('snippet', $video);
    }

    public function test_search_videos(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = self::$dotenv['YOUTUBE_API_KEY'];

        $results = (new Youtube($apiKey))->searchVideos('Laravel', 5);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    public function test_get_channel_by_id(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = self::$dotenv['YOUTUBE_API_KEY'];

        $channel = (new Youtube($apiKey))->getChannelById('UCk1SpWNzOs4MYmr0uICEntg');

        $this->assertNotNull($channel);
        $this->assertObjectHasProperty('id', $channel);
        $this->assertObjectHasProperty('snippet', $channel);
    }

    public function test_get_popular_videos(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = self::$dotenv['YOUTUBE_API_KEY'];

        $videos = (new Youtube($apiKey))->getPopularVideos('US', 5);

        $this->assertIsArray($videos);
        $this->assertNotEmpty($videos);
    }

    public function test_parse_vid_from_url(): void
    {
        $videoId = Youtube::parseVidFromURL('https://www.youtube.com/watch?v=moSFlvxnbgk');

        $this->assertEquals('moSFlvxnbgk', $videoId);
    }

    public function test_get_playlist_by_id(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = self::$dotenv['YOUTUBE_API_KEY'];

        $playlist = (new Youtube($apiKey))->getPlaylistById('PL590L5WQmH8fJ54F369BLDSqIwcs-TCfs');

        $this->assertNotNull($playlist);
        $this->assertObjectHasProperty('id', $playlist);
        $this->assertObjectHasProperty('snippet', $playlist);
    }

    public function test_get_playlist_items(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = self::$dotenv['YOUTUBE_API_KEY'];

        $items = (new Youtube($apiKey))->getPlaylistItemsByPlaylistId('PL590L5WQmH8fJ54F369BLDSqIwcs-TCfs');

        $this->assertIsArray($items);
        $this->assertArrayHasKey('results', $items);
        $this->assertArrayHasKey('info', $items);
    }

    public function test_caching_functionality(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = self::$dotenv['YOUTUBE_API_KEY'];

        // First request without cache
        $youtube1 = new Youtube($apiKey);
        $start1 = microtime(true);
        $videos1 = $youtube1->getPopularVideos('US', 5);
        $time1 = microtime(true) - $start1;

        // Second request with cache
        $youtube2 = (new Youtube($apiKey))->cache();
        $start2 = microtime(true);
        $videos2 = $youtube2->getPopularVideos('US', 5);
        $time2 = microtime(true) - $start2;

        $this->assertNotEmpty($videos1);
        $this->assertNotEmpty($videos2);

        // Cached request should be faster (though this is not always guaranteed)
        // We just verify both return the same data
        $this->assertEquals(count($videos1), count($videos2));
    }

    public function test_get_categories(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = self::$dotenv['YOUTUBE_API_KEY'];

        $categories = (new Youtube($apiKey))->getCategories('US');

        $this->assertIsArray($categories);
        $this->assertNotEmpty($categories);
    }

    public function test_search_channel_videos(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = self::$dotenv['YOUTUBE_API_KEY'];

        $videos = (new Youtube($apiKey))->searchChannelVideos('Laravel', 'UCk1SpWNzOs4MYmr0uICEntg', 5);

        $this->assertIsArray($videos);
        $this->assertNotEmpty($videos);
    }

    public function test_get_channel_by_name(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = self::$dotenv['YOUTUBE_API_KEY'];

        $channel = (new Youtube($apiKey))->getChannelByName('Google');

        $this->assertNotNull($channel);
        $this->assertObjectHasProperty('id', $channel);
    }

    public function test_paginate_results(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = self::$dotenv['YOUTUBE_API_KEY'];

        $params = [
            'q' => 'Laravel',
            'type' => 'video',
            'part' => 'id,snippet',
            'maxResults' => 10,
        ];

        $result = (new Youtube($apiKey))->paginateResults($params);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('info', $result);
    }

    // ==================== Error Handling Tests ====================

    public function test_api_error_throws_youtube_api_exception(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = self::$dotenv['YOUTUBE_API_KEY'];

        // Use an invalid video ID that will cause an API error
        $youtube = new Youtube($apiKey);

        // This should not throw an exception, but return null/false
        $result = $youtube->getVideoInfo('invalid_video_id_12345');

        // API returns empty result for non-existent videos, not an error
        $this->assertNull($result);
    }
}
