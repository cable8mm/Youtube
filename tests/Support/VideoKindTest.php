<?php

namespace Cable8mm\Youtube\Tests\Support;

use Cable8mm\Youtube\Support\VideoKind;
use PHPUnit\Framework\TestCase;

class VideoKindTest extends TestCase
{
    public function test_recorded_shorts_body_is_shorts(): void
    {
        $video = (object) [
            'id' => '2tpO9Kj0SeY',
            'snippet' => (object) [
                'title' => 'She Was Born to Control Sharks#shorts',
                'liveBroadcastContent' => 'none',
                'thumbnails' => (object) [
                    'default' => (object) ['url' => 'https://i.ytimg.com/vi/2tpO9Kj0SeY/default.jpg', 'width' => 120, 'height' => 90],
                    'shortsAspectRatio' => (object) ['url' => 'https://i.ytimg.com/vi/2tpO9Kj0SeY/sardefault.jpg', 'width' => 1778, 'height' => 3160],
                ],
            ],
            'contentDetails' => (object) ['duration' => 'PT2M59S'],
        ];

        $this->assertSame(VideoKind::SHORTS, VideoKind::of($video));
    }

    public function test_recorded_long_body_is_regular(): void
    {
        $video = (object) [
            'snippet' => (object) [
                'liveBroadcastContent' => 'none',
                'thumbnails' => (object) [
                    'default' => (object) ['url' => 'https://i.ytimg.com/vi/abc/default.jpg', 'width' => 120, 'height' => 90],
                ],
            ],
            'contentDetails' => (object) ['duration' => 'PT13M30S'],
        ];

        $this->assertSame(VideoKind::REGULAR, VideoKind::of($video));
    }

    public function test_live_broadcast_content_values_map_to_live_and_upcoming(): void
    {
        foreach (['live' => VideoKind::LIVE, 'upcoming' => VideoKind::UPCOMING] as $value => $expected) {
            $video = (object) [
                'snippet' => (object) [
                    'liveBroadcastContent' => $value,
                    'thumbnails' => (object) [],
                ],
            ];

            $this->assertSame($expected, VideoKind::of($video), $value);
        }
    }

    public function test_plain_short_bodies_are_shorts_by_duration(): void
    {
        foreach (['PT31S' => 'shorts', 'PT60S' => 'shorts', 'PT61S' => 'regular', 'PT4M13S' => 'regular'] as $duration => $expected) {
            $video = (object) [
                'snippet' => (object) [
                    'liveBroadcastContent' => 'none',
                    'thumbnails' => (object) [],
                ],
                'contentDetails' => (object) ['duration' => $duration],
            ];

            $this->assertSame($expected, VideoKind::of($video), $duration);
        }
    }

    public function test_live_wins_over_a_short_duration(): void
    {
        $video = (object) [
            'snippet' => (object) [
                'liveBroadcastContent' => 'live',
                'thumbnails' => (object) [],
            ],
            'contentDetails' => (object) ['duration' => 'PT30S'],
        ];

        $this->assertSame(VideoKind::LIVE, VideoKind::of($video));
    }

    public function test_video_without_a_snippet_is_regular(): void
    {
        $this->assertSame(VideoKind::REGULAR, VideoKind::of((object) ['id' => 'abc']));
    }

    public function test_video_without_a_duration_is_regular(): void
    {
        $video = (object) [
            'snippet' => (object) ['liveBroadcastContent' => 'none', 'thumbnails' => (object) []],
            'contentDetails' => (object) [],
        ];

        $this->assertSame(VideoKind::REGULAR, VideoKind::of($video));
    }
}
