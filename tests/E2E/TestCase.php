<?php

namespace Cable8mm\Youtube\Tests\E2E;

use Cable8mm\Youtube\Facades\Youtube;
use Cable8mm\Youtube\Tests\Env;
use Cable8mm\Youtube\Tests\TestCase as BaseTestCase;

/**
 * Base class for tests that call the live YouTube Data API.
 *
 * Runs only with `composer test:e2e`. A missing API key fails the test
 * instead of skipping it, so live coverage never disappears silently.
 */
abstract class TestCase extends BaseTestCase
{
    private const PLACEHOLDER_KEY = 'please_add_your_youtube_api_key';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('youtube.key', $this->apiKey());
        $this->app->forgetInstance(\Cable8mm\Youtube\Youtube::class);
        Youtube::clearResolvedInstance('youtube');
    }

    protected function apiKey(): string
    {
        $key = Env::get('YOUTUBE_API_KEY');

        if ($key === null || $key === self::PLACEHOLDER_KEY) {
            $this->fail('Set a valid YOUTUBE_API_KEY in .env to run the e2e suite.');
        }

        return $key;
    }
}
