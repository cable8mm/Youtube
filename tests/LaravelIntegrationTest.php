<?php

namespace Cable8mm\Youtube\Tests;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;
use Cable8mm\Youtube\Facades\Youtube;
use Cable8mm\Youtube\Rules\ValidYoutubeVideo;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;

class LaravelIntegrationTest extends TestCase
{
    private static bool $youtubeEnabled = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $dotenv = Dotenv::createImmutable(__DIR__.'/../')->load();
        self::$youtubeEnabled = isset($dotenv['YOUTUBE_ENABLED']) && $dotenv['YOUTUBE_ENABLED'] == 'true';
    }

    // ==================== Facade Tests ====================

    public function test_facade_is_accessible(): void
    {
        $this->assertTrue(class_exists('Youtube'));
        $this->assertInstanceOf(\Cable8mm\Youtube\Youtube::class, Youtube::getFacadeRoot());
    }

    public function test_facade_has_correct_config(): void
    {
        $youtube = Youtube::getFacadeRoot();

        $this->assertEquals('test_api_key', $youtube->getApiKey());
    }

    // ==================== Configuration Tests ====================

    public function test_config_is_loaded(): void
    {
        $this->assertNotNull(Config::get('youtube'));
        $this->assertNotNull(Config::get('youtube.key'));
    }

    public function test_config_key_matches(): void
    {
        $this->assertEquals('test_api_key', Config::get('youtube.key'));
    }

    // ==================== Cache Integration Tests ====================

    public function test_cache_works_with_laravel_cache(): void
    {
        // Clear any existing cache
        Cache::forget('test_key');

        // Test that cache is working
        $result = Cache::remember('test_key', 60, function () {
            return 'cached_value';
        });

        $this->assertEquals('cached_value', $result);
        $this->assertTrue(Cache::has('test_key'));
    }

    public function test_youtube_caching_integration(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        // Enable caching
        Youtube::cache();

        // First request
        $videos1 = Youtube::getPopularVideos('US', 5);

        // Second request should use cache
        $videos2 = Youtube::getPopularVideos('US', 5);

        $this->assertNotEmpty($videos1);
        $this->assertNotEmpty($videos2);
        $this->assertEquals(count($videos1), count($videos2));
    }

    // ==================== Validation Rule Tests ====================

    public function test_validation_rule_works_with_laravel_validator(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $validator = Validator::make(
            ['video_url' => 'https://www.youtube.com/watch?v=rie-hPVJ7Sw'],
            ['video_url' => ['required', new ValidYoutubeVideo]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_validation_rule_fails_for_invalid_url(): void
    {
        $validator = Validator::make(
            ['video_url' => 'https://example.com'],
            ['video_url' => ['required', new ValidYoutubeVideo]]
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('video_url', $validator->errors()->toArray());
    }

    // ==================== Service Container Tests ====================

    public function test_youtube_is_bound_in_container(): void
    {
        $this->assertTrue(app()->bound('youtube'));
        $this->assertTrue(app()->bound(\Cable8mm\Youtube\Youtube::class));
    }

    public function test_youtube_can_be_resolved_from_container(): void
    {
        $youtube = app(\Cable8mm\Youtube\Youtube::class);

        $this->assertInstanceOf(\Cable8mm\Youtube\Youtube::class, $youtube);
        $this->assertEquals('test_api_key', $youtube->getApiKey());
    }

    // ==================== Fluent Interface Tests ====================

    public function test_fluent_interface_with_facade(): void
    {
        Youtube::useHttpHost(true)
            ->setApiKey('another_key')
            ->cache()
            ->setCacheTtl(1800);

        $youtube = Youtube::getFacadeRoot();

        $this->assertEquals('another_key', $youtube->getApiKey());
    }

    // ==================== Error Handling Tests ====================

    public function test_custom_exception_is_thrown(): void
    {
        $this->expectException(YoutubeApiException::class);

        // This will throw an exception due to invalid API key format
        $youtube = new \Cable8mm\Youtube\Youtube('invalid_key');
        $youtube->getVideoInfo('test');
    }

    // ==================== Integration with Laravel Helpers ====================

    public function test_parse_vid_from_url_utility(): void
    {
        $videoId = Youtube::parseVidFromURL('https://www.youtube.com/watch?v=rie-hPVJ7Sw');

        $this->assertEquals('rie-hPVJ7Sw', $videoId);
    }

    public function test_youtube_methods_are_accessible_via_facade(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        // Test that we can call methods via Facade
        $video = Youtube::getVideoInfo('rie-hPVJ7Sw');

        $this->assertNotNull($video);
        $this->assertObjectHasProperty('id', $video);
    }
}
