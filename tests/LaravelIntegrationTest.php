<?php

namespace Cable8mm\Youtube\Tests;

use Cable8mm\Youtube\Facades\Youtube;
use Cable8mm\Youtube\Rules\ValidYoutubeVideo;
use Cable8mm\Youtube\Tests\Resources\FakeApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;

class LaravelIntegrationTest extends TestCase
{
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

    public function test_api_client_serves_repeat_requests_from_laravel_cache(): void
    {
        Cache::flush();

        $client = (new FakeApiClient(['{"etag":"first"}', '{"etag":"second"}']))->cache();

        $this->assertSame('{"etag":"first"}', $client->call('videos.list', ['id' => 'abc']));
        $this->assertSame('{"etag":"first"}', $client->call('videos.list', ['id' => 'abc']));
        $this->assertCount(1, $client->requests);
    }

    public function test_api_client_does_not_cache_error_responses(): void
    {
        Cache::flush();

        $error = '{"error":{"code":403,"message":"Forbidden"}}';
        $client = (new FakeApiClient([$error, '{"items":[]}']))->cache();

        $this->assertSame($error, $client->call('videos.list', ['id' => 'abc']));
        $this->assertSame('{"items":[]}', $client->call('videos.list', ['id' => 'abc']));
        $this->assertCount(2, $client->requests);
    }

    public function test_api_client_does_not_cache_invalid_json(): void
    {
        Cache::flush();

        $client = (new FakeApiClient(['', '{"items":[]}']))->cache();

        $client->call('videos.list', ['id' => 'abc']);
        $client->call('videos.list', ['id' => 'abc']);

        $this->assertCount(2, $client->requests);
    }

    // ==================== Validation Rule Tests ====================

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

    public function test_facade_and_container_keys_share_one_client(): void
    {
        $byAlias = app('youtube');

        $this->assertSame($byAlias, app(\Cable8mm\Youtube\Youtube::class));
        $this->assertSame($byAlias, Youtube::getFacadeRoot());

        Youtube::setApiKey('changed');

        $this->assertSame('changed', app('youtube')->getApiKey());
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

    // ==================== Integration with Laravel Helpers ====================

    public function test_parse_vid_from_url_utility(): void
    {
        $videoId = Youtube::parseVidFromURL('https://www.youtube.com/watch?v=rie-hPVJ7Sw');

        $this->assertEquals('rie-hPVJ7Sw', $videoId);
    }
}
