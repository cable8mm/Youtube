<?php

namespace Cable8mm\Youtube\Tests\Rules;

use Cable8mm\Youtube\Resources\Videos;
use Cable8mm\Youtube\Rules\ValidYoutubeVideo;
use Cable8mm\Youtube\Youtube;
use PHPUnit\Framework\TestCase;

class ValidYoutubeVideoTest extends TestCase
{
    private ValidYoutubeVideo $rule;

    protected function setUp(): void
    {
        // Plain PHPUnit has no container, so hand the rule a client directly.
        $this->rule = new ValidYoutubeVideo(new Youtube('test_key'));
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

    // ==================== Video Lookup Tests ====================

    public function test_validate_fails_when_video_does_not_exist(): void
    {
        $rule = new ValidYoutubeVideo($this->youtubeReturning(null));

        $errors = [];
        $rule->validate('youtube_video_url', 'https://www.youtube.com/watch?v=missing0000', function ($message) use (&$errors) {
            $errors[] = $message;
        });

        $this->assertSame(['The :attribute does not exist on Youtube.'], $errors);
    }

    public function test_validate_passes_when_video_exists(): void
    {
        $rule = new ValidYoutubeVideo($this->youtubeReturning((object) ['id' => 'rie-hPVJ7Sw']));

        $errors = [];
        $rule->validate('youtube_video_url', 'https://www.youtube.com/watch?v=rie-hPVJ7Sw', function ($message) use (&$errors) {
            $errors[] = $message;
        });

        $this->assertEmpty($errors);
    }

    private function youtubeReturning(?object $video): Youtube
    {
        return new class($video) extends Youtube
        {
            public function __construct(private ?object $video)
            {
                parent::__construct('test_key');
            }

            public function getVideoInfo(array|string $vId, array $part = Videos::DEFAULT_PART)
            {
                return $this->video;
            }
        };
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
