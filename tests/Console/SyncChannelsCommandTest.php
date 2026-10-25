<?php

namespace Cable8mm\Youtube\Tests\Console;

use Cable8mm\Youtube\Jobs\SyncChannelVideos;
use Cable8mm\Youtube\Models\YoutubeChannel;
use Cable8mm\Youtube\Sync\QuotaBlock;
use Cable8mm\Youtube\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

class SyncChannelsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('youtube.sync.queue', 'youtube');
        $app['config']->set('youtube.sync.max_pages', 3);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    private function channel(string $channelId, bool $active = true): YoutubeChannel
    {
        return YoutubeChannel::create(['channel_id' => $channelId, 'title' => $channelId, 'is_active' => $active]);
    }

    /**
     * @return array<int, array{int, ?int, ?string}> youtubeChannelId, maxPages, queue of each pushed job
     */
    private function pushed(): array
    {
        return Queue::pushed(SyncChannelVideos::class)
            ->map(fn ($job) => [$job->youtubeChannelId, $job->maxPages, $job->queue])
            ->values()
            ->all();
    }

    public function test_pushes_one_job_per_active_channel(): void
    {
        $a = $this->channel('UCaaaaaaaaaaaaaaaaaaaaaa');
        $this->channel('UCbbbbbbbbbbbbbbbbbbbbbb', false);
        $c = $this->channel('UCcccccccccccccccccccccc');

        $this->artisan('youtube:sync')->assertExitCode(0);

        $this->assertSame([[$a->id, 3, 'youtube'], [$c->id, 3, 'youtube']], $this->pushed());
    }

    public function test_full_pushes_jobs_without_a_page_limit(): void
    {
        $a = $this->channel('UCaaaaaaaaaaaaaaaaaaaaaa');

        $this->artisan('youtube:sync', ['--full' => true])->assertExitCode(0);

        $this->assertSame([[$a->id, null, 'youtube']], $this->pushed());
    }

    public function test_channel_option_pushes_inactive_channels_and_reports_unknown_ids(): void
    {
        $this->channel('UCaaaaaaaaaaaaaaaaaaaaaa');
        $b = $this->channel('UCbbbbbbbbbbbbbbbbbbbbbb', false);

        $this->artisan('youtube:sync', ['--channel' => ['UCbbbbbbbbbbbbbbbbbbbbbb', 'UCzzzzzzzzzzzzzzzzzzzzzz']])
            ->expectsOutput('unknown channel UCzzzzzzzzzzzzzzzzzzzzzz')
            ->assertExitCode(0);

        $this->assertSame([[$b->id, 3, 'youtube']], $this->pushed());
    }

    public function test_quota_block_pushes_nothing(): void
    {
        $this->channel('UCaaaaaaaaaaaaaaaaaaaaaa');
        $until = (new QuotaBlock)->start();

        $this->artisan('youtube:sync')
            ->expectsOutput('quota blocked until '.$until->toIso8601String())
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }
}
