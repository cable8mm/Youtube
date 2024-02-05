<?php

namespace Cable8mm\Youtube\Tests;

use Cable8mm\Youtube\Youtube;
use Carbon\Carbon;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

class YoutubeTest extends TestCase
{
    private array $dotenv;

    private bool $youtubeEnabled;

    public function setUp(): void
    {
        $this->dotenv = Dotenv::createImmutable(__DIR__.'/../')->load();

        $this->youtubeEnabled = isset($this->dotenv['YOUTUBE_ENABLED']) && $this->dotenv['YOUTUBE_ENABLED'] == 'true' ? true : false;
    }

    public function test_getChannelVideos(): void
    {
        if ($this->youtubeEnabled) {
            $apiKey = $this->dotenv['YOUTUBE_API_KEY'];

            $channelId = $this->dotenv['YOUTUBE_CHANNEL_ID'];

            $publishedBefore = Carbon::now()->toRfc3339String();

            $videos = (new Youtube($apiKey))->getChannelVideos($channelId, 1, $publishedBefore);

            $this->assertNotEmpty($videos);
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_getChannelVideos_at_second(): void
    {
        if ($this->youtubeEnabled) {

            $apiKey = $this->dotenv['YOUTUBE_API_KEY'];

            $channelId = $this->dotenv['YOUTUBE_CHANNEL_ID'];

            $publishedBefore = Carbon::now()->toRfc3339String();

            $video = (new Youtube($apiKey))->getChannelVideos($channelId, 1, $publishedBefore)[0];

            $secondVideo = (new Youtube($apiKey))->getChannelVideos($channelId, 1, $video->snippet->publishedAt)[0];

            $this->assertNotEquals($video->id->videoId, $secondVideo->id->videoId);
        } else {
            $this->assertTrue(true);
        }
    }
}
