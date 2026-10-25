<?php

namespace Cable8mm\Youtube\Tests\E2E;

use Cable8mm\Youtube\Models\YoutubeChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SyncChannelsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_sync_command_runs_the_job_on_the_sync_connection(): void
    {
        $channel = YoutubeChannel::create(['channel_id' => 'UCk1SpWNzOs4MYmr0uICEntg', 'title' => 'XDA']);

        $this->artisan('youtube:sync', ['--channel' => ['UCk1SpWNzOs4MYmr0uICEntg']])->assertExitCode(0);

        $channel->refresh();
        $this->assertSame('ok', $channel->sync_status);
        $this->assertGreaterThan(0, $channel->videos()->count());

        $video = $channel->videos()->first();
        $this->assertNotEmpty($video->title);
        $this->assertNotNull($video->published_at);
        $this->assertNotNull($video->view_count);
    }
}
