<?php

namespace Cable8mm\Youtube\Tests\E2E;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;
use Cable8mm\Youtube\Facades\Youtube;
use Cable8mm\Youtube\Rules\ValidYoutubeVideo;
use Illuminate\Support\Facades\Validator;

class LaravelIntegrationTest extends TestCase
{
    public function test_youtube_caching_integration(): void
    {
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

    public function test_validation_rule_works_with_laravel_validator(): void
    {
        $validator = Validator::make(
            ['video_url' => 'https://www.youtube.com/watch?v=rie-hPVJ7Sw'],
            ['video_url' => ['required', new ValidYoutubeVideo]]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_custom_exception_is_thrown(): void
    {
        $this->expectException(YoutubeApiException::class);

        // Rejected by the live API (code 400)
        $youtube = new \Cable8mm\Youtube\Youtube('invalid_key');
        $youtube->getVideoInfo('test');
    }

    public function test_youtube_methods_are_accessible_via_facade(): void
    {
        // Test that we can call methods via Facade
        $video = Youtube::getVideoInfo('rie-hPVJ7Sw');

        $this->assertNotNull($video);
        $this->assertObjectHasProperty('id', $video);
    }
}
