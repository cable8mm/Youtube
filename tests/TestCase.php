<?php

namespace Cable8mm\Youtube\Tests;

use Cable8mm\Youtube\Facades\Youtube;
use Cable8mm\Youtube\YoutubeServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            YoutubeServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Youtube' => Youtube::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('youtube.key', 'test_api_key');
    }

    /**
     * Skip unless live API tests are enabled, then rebind the client with the real key.
     */
    protected function useLiveApiKey(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $this->app['config']->set('youtube.key', Env::get('YOUTUBE_API_KEY'));
        $this->app->forgetInstance(\Cable8mm\Youtube\Youtube::class);
        Youtube::clearResolvedInstance('youtube');
    }
}
