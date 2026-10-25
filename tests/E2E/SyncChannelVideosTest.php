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

    public function test_private_and_deleted_videos_are_flagged_and_a_returned_video_is_cleared(): void
    {
        // Observed in PS §18.3: rie-hPVJ7Sw is returned, private 3x_bMyRvJu0 and deleted Flf-L9FjaV8 are not.
        $channel = YoutubeChannel::create(['channel_id' => self::CHANNEL_ID, 'title' => 'XDA']);
        foreach (['3x_bMyRvJu0' => null, 'Flf-L9FjaV8' => null, 'rie-hPVJ7Sw' => '2026-09-01 00:00:00'] as $videoId => $unavailableAt) {
            $channel->videos()->create(['video_id' => $videoId, 'title' => $videoId, 'published_at' => '2026-01-01 00:00:00', 'unavailable_at' => $unavailableAt]);
        }
        Event::fake([ChannelSynced::class]);

        Bus::dispatchSync(new SyncChannelVideos($channel->id));

        $videos = $channel->videos()->whereIn('video_id', ['3x_bMyRvJu0', 'Flf-L9FjaV8', 'rie-hPVJ7Sw'])->get()->keyBy('video_id');
        $this->assertCount(3, $videos);
        $this->assertNotNull($videos['3x_bMyRvJu0']->unavailable_at);
        $this->assertNotNull($videos['Flf-L9FjaV8']->unavailable_at);
        $this->assertNull($videos['rie-hPVJ7Sw']->unavailable_at);
        $this->assertNotSame('rie-hPVJ7Sw', $videos['rie-hPVJ7Sw']->title);
        Event::assertDispatched(ChannelSynced::class, fn ($e) => $e->unavailable === 2);
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
