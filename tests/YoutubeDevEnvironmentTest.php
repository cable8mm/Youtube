<?php

namespace Cable8mm\Youtube\Tests;

use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

class YoutubeDevEnvironmentTest extends TestCase
{
    private array $dotenv;

    public function setUp(): void
    {
        $this->dotenv = Dotenv::createImmutable(__DIR__.'/../')->load();
    }

    public function test_is_exist_youtube_api_key(): void
    {
        $this->assertNotEmpty($this->dotenv['YOUTUBE_API_KEY']);
    }

    public function test_env(): void
    {
        $expected = 'UCNgEhs22fJzCTvl99AHlg7A';

        $actual = $this->dotenv['YOUTUBE_CHANNEL_ID'];

        $this->assertEquals($expected, $actual);
    }
}
