<?php

namespace Cable8mm\Youtube\Tests;

use PHPUnit\Framework\TestCase;

class YoutubeDevEnvironmentTest extends TestCase
{
    public function test_is_exist_youtube_api_key(): void
    {
        if (Env::get('YOUTUBE_API_KEY') === null) {
            $this->markTestSkipped('YOUTUBE_API_KEY is not set in .env file');
        }

        $this->assertNotEmpty(Env::get('YOUTUBE_API_KEY'));
    }
}
