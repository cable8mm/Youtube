<?php

namespace Cable8mm\Youtube\Tests;

use Cable8mm\Youtube\Facades\Youtube;
use Cable8mm\Youtube\YoutubeServiceProvider;
use Maatwebsite\Excel\ExcelServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            YoutubeServiceProvider::class,
            ExcelServiceProvider::class,
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
}
