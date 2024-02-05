<?php

namespace Cable8mm\Youtube\Tests;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

class YoutubeDevEnvironmentTest extends TestCase
{
    private static array $dotenv;

    public static function setUpBeforeClass(): void
    {
        self::$dotenv = Dotenv::createImmutable(__DIR__.'/../')->load();
    }

    public function test_is_exist_youtube_api_key(): void
    {
        $this->assertNotEmpty(self::$dotenv['YOUTUBE_API_KEY']);
    }

    public function test_is_exist_youtube_channel_id(): void
    {
        $expected = 'UCNgEhs22fJzCTvl99AHlg7A';

        $actual = self::$dotenv['YOUTUBE_CHANNEL_ID'];

        $this->assertEquals($expected, $actual);
    }
}
