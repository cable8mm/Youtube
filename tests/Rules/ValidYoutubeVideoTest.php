<?php

namespace Cable8mm\Youtube\Tests\Rules;

use Cable8mm\Youtube\Rules\ValidYoutubeVideo;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

class ValidYoutubeVideoTest extends TestCase
{
    private ValidYoutubeVideo $rule;

    private static bool $youtubeEnabled;

    public static function setUpBeforeClass(): void
    {
        $dotenv = Dotenv::createImmutable(__DIR__.'/../../')->load();
        self::$youtubeEnabled = isset($dotenv['YOUTUBE_ENABLED']) && $dotenv['YOUTUBE_ENABLED'] == 'true';
    }

    protected function setUp(): void
    {
        $this->rule = new ValidYoutubeVideo;
    }

    // ==================== Valid URL Tests ====================

    public function test_passes_with_standard_youtube_url(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $this->assertTrue($this->rule->passes('youtube_video_url', 'https://www.youtube.com/watch?v=rie-hPVJ7Sw'));
    }

    public function test_passes_with_short_youtube_url(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $this->assertTrue($this->rule->passes('youtube_video_url', 'https://youtu.be/rie-hPVJ7Sw'));
    }

    public function test_passes_with_embed_youtube_url(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $this->assertTrue($this->rule->passes('youtube_video_url', 'https://www.youtube.com/embed/rie-hPVJ7Sw'));
    }

    public function test_passes_with_url_containing_parameters(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $this->assertTrue($this->rule->passes('youtube_video_url', 'https://www.youtube.com/watch?v=rie-hPVJ7Sw&t=123'));
    }

    public function test_passes_with_www_subdomain(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $this->assertTrue($this->rule->passes('youtube_video_url', 'https://www.youtube.com/watch?v=rie-hPVJ7Sw'));
    }

    public function test_passes_without_www_subdomain(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $this->assertTrue($this->rule->passes('youtube_video_url', 'https://youtube.com/watch?v=rie-hPVJ7Sw'));
    }

    public function test_passes_with_http_protocol(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $this->assertTrue($this->rule->passes('youtube_video_url', 'http://www.youtube.com/watch?v=rie-hPVJ7Sw'));
    }

    // ==================== Invalid URL Tests ====================

    public function test_fails_with_invalid_domain(): void
    {
        $this->assertFalse($this->rule->passes('youtube_video_url', 'https://example.com/watch?v=rie-hPVJ7Sw'));
    }

    public function test_fails_with_missing_video_id(): void
    {
        $this->assertFalse($this->rule->passes('youtube_video_url', 'https://www.youtube.com/watch'));
    }

    public function test_fails_with_empty_string(): void
    {
        $this->assertFalse($this->rule->passes('youtube_video_url', ''));
    }

    public function test_fails_with_random_string(): void
    {
        $this->assertFalse($this->rule->passes('youtube_video_url', 'not a youtube url'));
    }

    public function test_fails_with_vimeo_url(): void
    {
        $this->assertFalse($this->rule->passes('youtube_video_url', 'https://vimeo.com/123456789'));
    }

    // ==================== Error Message Tests ====================

    public function test_returns_correct_error_message(): void
    {
        // Test that message contains expected text (without using __() helper)
        $message = $this->rule->message();
        $this->assertStringContainsString('The supplied URL does not look like a Youtube URL', $message);
    }

    // ==================== Edge Cases ====================

    public function test_passes_with_different_video_ids(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $urls = [
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=9bZkp7q19f0',
            'https://youtu.be/jNQXAC9IVRw',
            'https://www.youtube.com/embed/jNQXAC9IVRw',
        ];

        foreach ($urls as $url) {
            $this->assertTrue(
                $this->rule->passes('youtube_video_url', $url),
                "Failed for URL: {$url}"
            );
        }
    }

    public function test_fails_with_youtube_but_not_video_url(): void
    {
        // YouTube URLs that are not video URLs
        $urls = [
            'https://www.youtube.com/channel/UCk1SpWNzOs4MYmr0uICEntg',
            'https://www.youtube.com/user/Google',
            'https://www.youtube.com/feed/trending',
        ];

        foreach ($urls as $url) {
            $this->assertFalse(
                $this->rule->passes('youtube_video_url', $url),
                "Should fail for URL: {$url}"
            );
        }
    }
}
