<?php

namespace Cable8mm\Youtube\Tests\E2E;

use Cable8mm\Youtube\Events\ChannelSynced;
use Cable8mm\Youtube\Events\ChannelSyncFailed;
use Cable8mm\Youtube\Jobs\SyncChannelVideos;
use Cable8mm\Youtube\Models\YoutubeChannel;
use Cable8mm\Youtube\Youtube;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

class SyncChannelVideosTest extends TestCase
{
    use RefreshDatabase;

    private const CHANNEL_ID = 'UCk1SpWNzOs4MYmr0uICEntg';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('cache.default', 'array');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_sync_stores_the_newest_uploads_and_a_second_sync_adds_none(): void
    {
        // uploads_playlist_id is null, so the job looks the channel up first.
        $channel = YoutubeChannel::create(['channel_id' => self::CHANNEL_ID, 'title' => self::CHANNEL_ID]);

        Bus::dispatchSync(new SyncChannelVideos($channel->id));

        $channel->refresh();
        $this->assertSame('ok', $channel->sync_status);
        $this->assertNotNull($channel->last_synced_at);
        $this->assertStringStartsWith('UU', $channel->uploads_playlist_id);
        $this->assertSame('XDA', $channel->title);

        $count = $channel->videos()->count();
        $this->assertGreaterThan(0, $count);
        $this->assertLessThanOrEqual(50, $count);

        foreach ($channel->videos as $video) {
            $this->assertNotEmpty($video->video_id);
            $this->assertNotEmpty($video->title);
            $this->assertNotNull($video->published_at);
            $this->assertNotNull($video->duration);
            $this->assertNotNull($video->thumbnail_url);
            $this->assertNotNull($video->view_count, $video->video_id);
        }

        Event::fake([ChannelSynced::class]);
        Bus::dispatchSync(new SyncChannelVideos($channel->id));

        $this->assertSame($count, $channel->videos()->count());
        Event::assertDispatched(ChannelSynced::class, fn ($e) => $e->created === 0 && $e->updated === $count);
    }

    public function test_invalid_api_key_is_recorded_as_a_failed_sync(): void
    {
        $this->app['config']->set('youtube.key', 'invalid_api_key');
        $this->app->forgetInstance(Youtube::class);

        $channel = YoutubeChannel::create(['channel_id' => self::CHANNEL_ID, 'title' => 'XDA']);
        Event::fake([ChannelSyncFailed::class]);

        Bus::dispatchSync(new SyncChannelVideos($channel->id));

        $error = $channel->syncErrors()->sole();
        $this->assertSame('invalid_key', $error->reason);
        $this->assertSame(400, $error->http_code);

        $channel->refresh();
        $this->assertSame('failed', $channel->sync_status);
        $this->assertSame('invalid_key', $channel->last_error_reason);
        Event::assertDispatched(ChannelSyncFailed::class, fn ($e) => $e->reason === 'invalid_key');
    }
}
