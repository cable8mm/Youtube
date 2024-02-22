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

        self::$youtubeEnabled = isset(self::$dotenv['YOUTUBE_ENABLED']) && self::$dotenv['YOUTUBE_ENABLED'] == 'true' ? true : false;
    }

    public function test_getChannelVideos(): void
    {
        if (self::$youtubeEnabled) {
            $apiKey = self::$dotenv['YOUTUBE_API_KEY'];

            $channelId = self::$dotenv['YOUTUBE_CHANNEL_ID'];

            $publishedBefore = Carbon::now()->toRfc3339String();

            $videos = (new Youtube($apiKey))->getChannelVideos($channelId, 1, $publishedBefore)['results'];

            $this->assertNotEmpty($videos);
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_getChannelVideos_at_second(): void
    {
        if (self::$youtubeEnabled) {

            $apiKey = self::$dotenv['YOUTUBE_API_KEY'];

            $channelId = self::$dotenv['YOUTUBE_CHANNEL_ID'];

            $publishedBefore = Carbon::now()->toRfc3339String();

            $video = (new Youtube($apiKey))->getChannelVideos($channelId, 1, $publishedBefore)['results'][0];

            $secondVideo = (new Youtube($apiKey))->getChannelVideos($channelId, 1, $video->snippet->publishedAt)['results'][0];

            $this->assertNotEquals($video->id->videoId, $secondVideo->id->videoId);
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_getChannelVideos_in_future()
    {
        if (self::$youtubeEnabled) {
            $apiKey = self::$dotenv['YOUTUBE_API_KEY'];

            $channelId = self::$dotenv['YOUTUBE_CHANNEL_ID'];

            $publishedAfter = Carbon::now()->toRfc3339String();

            $videos = (new Youtube($apiKey))->getChannelVideos($channelId, 1, $publishedAfter, true)['results'];

            $this->assertEmpty($videos);
        } else {
            $this->assertTrue(true);
        }
    }
}
