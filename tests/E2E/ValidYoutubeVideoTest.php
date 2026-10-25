<?php

namespace Cable8mm\Youtube\Tests\E2E;

use Cable8mm\Youtube\Rules\ValidYoutubeVideo;

class ValidYoutubeVideoTest extends TestCase
{
    private ValidYoutubeVideo $rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule = new ValidYoutubeVideo;
    }

    // ==================== Valid URL Tests ====================

    public function test_validate_passes_with_standard_youtube_url(): void
    {
        $errors = [];
        $this->rule->validate('youtube_video_url', 'https://www.youtube.com/watch?v=rie-hPVJ7Sw', function ($message) use (&$errors) {
            $errors[] = $message;
        });

        $this->assertEmpty($errors, 'Validation should pass for valid YouTube URL');
    }

    public function test_validate_passes_with_music_and_nocookie_urls(): void
    {
        foreach (['https://music.youtube.com/watch?v=rie-hPVJ7Sw', 'https://www.youtube-nocookie.com/embed/rie-hPVJ7Sw'] as $url) {
            $errors = [];
            $this->rule->validate('youtube_video_url', $url, function ($message) use (&$errors) {
                $errors[] = $message;
            });

            $this->assertEmpty($errors, $url);
        }
    }

    public function test_validate_passes_with_short_youtube_url(): void
    {
        $errors = [];
        $this->rule->validate('youtube_video_url', 'https://youtu.be/rie-hPVJ7Sw', function ($message) use (&$errors) {
            $errors[] = $message;
        });

        $this->assertEmpty($errors, 'Validation should pass for valid short YouTube URL');
    }

    public function test_validate_passes_with_embed_youtube_url(): void
    {
        $errors = [];
        $this->rule->validate('youtube_video_url', 'https://www.youtube.com/embed/rie-hPVJ7Sw', function ($message) use (&$errors) {
            $errors[] = $message;
        });

        $this->assertEmpty($errors, 'Validation should pass for valid embed YouTube URL');
    }

    public function test_validate_passes_with_url_containing_parameters(): void
    {
        $errors = [];
        $this->rule->validate('youtube_video_url', 'https://www.youtube.com/watch?v=rie-hPVJ7Sw&t=123', function ($message) use (&$errors) {
            $errors[] = $message;
        });

        $this->assertEmpty($errors, 'Validation should pass for YouTube URL with parameters');
    }
}
