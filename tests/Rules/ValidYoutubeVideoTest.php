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

    public function test_validate_passes_with_standard_youtube_url(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $errors = [];
        $this->rule->validate('youtube_video_url', 'https://www.youtube.com/watch?v=rie-hPVJ7Sw', function ($message) use (&$errors) {
            $errors[] = $message;
        });

        $this->assertEmpty($errors, 'Validation should pass for valid YouTube URL');
    }

    public function test_validate_passes_with_short_youtube_url(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $errors = [];
        $this->rule->validate('youtube_video_url', 'https://youtu.be/rie-hPVJ7Sw', function ($message) use (&$errors) {
            $errors[] = $message;
        });

        $this->assertEmpty($errors, 'Validation should pass for valid short YouTube URL');
    }

    public function test_validate_passes_with_embed_youtube_url(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $errors = [];
        $this->rule->validate('youtube_video_url', 'https://www.youtube.com/embed/rie-hPVJ7Sw', function ($message) use (&$errors) {
            $errors[] = $message;
        });

        $this->assertEmpty($errors, 'Validation should pass for valid embed YouTube URL');
    }

    public function test_validate_passes_with_url_containing_parameters(): void
    {
        if (! self::$youtubeEnabled) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $errors = [];
        $this->rule->validate('youtube_video_url', 'https://www.youtube.com/watch?v=rie-hPVJ7Sw&t=123', function ($message) use (&$errors) {
            $errors[] = $message;
        });

        $this->assertEmpty($errors, 'Validation should pass for YouTube URL with parameters');
    }

    // ==================== Invalid URL Tests ====================

    public function test_validate_fails_with_invalid_domain(): void
    {
        $errors = [];
        $this->rule->validate('youtube_video_url', 'https://example.com/watch?v=rie-hPVJ7Sw', function ($message) use (&$errors) {
            $errors[] = $message;
        });

        $this->assertNotEmpty($errors, 'Validation should fail for invalid domain');
        // Accept any error message since it could be from URL parsing or API call
        $this->assertNotEmpty($errors[0]);
    }

    public function test_validate_fails_with_missing_video_id(): void
    {
        $errors = [];
        $this->rule->validate('youtube_video_url', 'https://www.youtube.com/watch', function ($message) use (&$errors) {
            $errors[] = $message;
        });

        $this->assertNotEmpty($errors, 'Validation should fail for URL without video ID');
    }

    public function test_validate_fails_with_empty_string(): void
    {
        $errors = [];
        $this->rule->validate('youtube_video_url', '', function ($message) use (&$errors) {
            $errors[] = $message;
        });

        $this->assertNotEmpty($errors, 'Validation should fail for empty string');
    }

    public function test_validate_fails_with_random_string(): void
    {
        $errors = [];
        $this->rule->validate('youtube_video_url', 'not a youtube url', function ($message) use (&$errors) {
            $errors[] = $message;
        });

        $this->assertNotEmpty($errors, 'Validation should fail for random string');
    }

    public function test_validate_fails_with_vimeo_url(): void
    {
        $errors = [];
        $this->rule->validate('youtube_video_url', 'https://vimeo.com/123456789', function ($message) use (&$errors) {
            $errors[] = $message;
        });

        $this->assertNotEmpty($errors, 'Validation should fail for Vimeo URL');
    }

    // ==================== Edge Cases ====================

    public function test_validate_fails_with_youtube_but_not_video_url(): void
    {
        // YouTube URLs that are not video URLs
        $urls = [
            'https://www.youtube.com/channel/UCk1SpWNzOs4MYmr0uICEntg',
            'https://www.youtube.com/user/Google',
            'https://www.youtube.com/feed/trending',
        ];

        foreach ($urls as $url) {
            $errors = [];
            $this->rule->validate('youtube_video_url', $url, function ($message) use (&$errors) {
                $errors[] = $message;
            });

            $this->assertNotEmpty($errors, "Should fail for URL: {$url}");
        }
    }
}
