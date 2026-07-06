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
        if (! isset(self::$dotenv['YOUTUBE_API_KEY']) || empty(self::$dotenv['YOUTUBE_API_KEY'])) {
            $this->markTestSkipped('YOUTUBE_API_KEY is not set in .env file');
        }

        $this->assertNotEmpty(self::$dotenv['YOUTUBE_API_KEY']);
    }
}
