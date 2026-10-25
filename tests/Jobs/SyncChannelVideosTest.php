<?php

namespace Cable8mm\Youtube\Tests\Jobs;

use Cable8mm\Youtube\Events\ChannelSynced;
use Cable8mm\Youtube\Events\ChannelSyncFailed;
use Cable8mm\Youtube\Events\QuotaExceeded;
use Cable8mm\Youtube\Jobs\SyncChannelVideos;
use Cable8mm\Youtube\Models\YoutubeChannel;
use Cable8mm\Youtube\Models\YoutubeVideo;
use Cable8mm\Youtube\Sync\QuotaBlock;
use Cable8mm\Youtube\Tests\Resources\FakeApiClient;
use Cable8mm\Youtube\Tests\Resources\FakeYoutube;
use Cable8mm\Youtube\Tests\TestCase;
use Cable8mm\Youtube\Youtube;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

class SyncChannelVideosTest extends TestCase
{
    use RefreshDatabase;

    private const CHANNEL_ID = 'UCk1SpWNzOs4MYmr0uICEntg';

    private const UPLOADS = 'UUk1SpWNzOs4MYmr0uICEntg';

    private FakeApiClient $fake;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->fake = new FakeApiClient;
        $this->app->instance(Youtube::class, new FakeYoutube($this->fake));
        Event::fake([ChannelSynced::class, ChannelSyncFailed::class, QuotaExceeded::class]);
    }

    // Bodies follow the documented playlistItems, videos and channels list responses.

    private static function playlistPage(array $videoIds, ?string $nextPageToken = null): string
    {
        return json_encode(array_filter([
            'kind' => 'youtube#playlistItemListResponse',
            'etag' => 'e',
            'nextPageToken' => $nextPageToken,
            'pageInfo' => ['totalResults' => count($videoIds), 'resultsPerPage' => 50],
            'items' => array_map(fn ($id) => [
                'kind' => 'youtube#playlistItem',
                'id' => 'item-'.$id,
                'contentDetails' => ['videoId' => $id],
            ], $videoIds),
        ], fn ($value) => $value !== null));
    }

    private static function video(string $id, array $statistics = ['viewCount' => '10', 'likeCount' => '2', 'commentCount' => '1']): array
    {
        return [
            'kind' => 'youtube#video',
            'id' => $id,
            'snippet' => [
                'publishedAt' => '2026-10-01T12:00:00Z',
                'title' => 'Title '.$id,
                'description' => 'Description '.$id,
                'thumbnails' => [
                    'default' => ['url' => "https://i.ytimg.com/vi/$id/default.jpg"],
                    'high' => ['url' => "https://i.ytimg.com/vi/$id/hqdefault.jpg"],
                ],
            ],
            'contentDetails' => ['duration' => 'PT4M13S'],
            'statistics' => $statistics,
        ];
    }

    private static function videoList(array $videos): string
    {
        return json_encode(['kind' => 'youtube#videoListResponse', 'etag' => 'e', 'items' => $videos]);
    }

    private static function errorBody(int $code, string $message, string $reason): string
    {
        return json_encode(['error' => ['code' => $code, 'message' => $message, 'errors' => [['reason' => $reason]]]]);
    }

    private function channel(array $attributes = []): YoutubeChannel
    {
        return YoutubeChannel::create($attributes + [
            'channel_id' => self::CHANNEL_ID,
            'title' => 'XDA',
            'uploads_playlist_id' => self::UPLOADS,
        ]);
    }

    private function requestsTo(string $resource): array
    {
        return array_values(array_filter(
            $this->fake->requests,
            fn ($request) => str_ends_with($request['url'], '/'.$resource)
        ));
    }

    private function sync(YoutubeChannel $channel, ?int $maxPages = 1): void
    {
        Bus::dispatchSync(new SyncChannelVideos($channel->id, $maxPages));
    }

    public function test_job_is_queued_once(): void
    {
        $job = new SyncChannelVideos(1);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame(1, $job->tries);
        $this->assertSame(1, $job->maxPages);
    }

    public function test_sync_creates_video_records_and_marks_channel_ok(): void
    {
        $channel = $this->channel();
        array_push(
            $this->fake->responses,
            self::playlistPage(['v1', 'v2']),
            self::videoList([self::video('v1'), self::video('v2')]),
        );

        $this->sync($channel);

        $video = YoutubeVideo::where('video_id', 'v1')->first();
        $this->assertSame($channel->id, $video->youtube_channel_id);
        $this->assertSame('Title v1', $video->title);
        $this->assertSame('Description v1', $video->description);
        $this->assertSame('2026-10-01 12:00:00', $video->published_at->format('Y-m-d H:i:s'));
        $this->assertSame('PT4M13S', $video->duration);
        $this->assertSame('https://i.ytimg.com/vi/v1/hqdefault.jpg', $video->thumbnail_url);
        $this->assertSame([10, 2, 1], [$video->view_count, $video->like_count, $video->comment_count]);
        $this->assertNull($video->unavailable_at);
        $this->assertSame(2, YoutubeVideo::count());

        $channel->refresh();
        $this->assertSame('ok', $channel->sync_status);
        $this->assertNotNull($channel->last_synced_at);
        $this->assertNull($channel->last_error_reason);

        $videoRequest = $this->requestsTo('videos')[0]['params'];
        $this->assertSame('v1,v2', $videoRequest['id']);
        $this->assertSame('id,snippet,contentDetails,statistics', $videoRequest['part']);
        $this->assertSame(self::UPLOADS, $this->requestsTo('playlistItems')[0]['params']['playlistId']);
        $this->assertSame(50, $this->requestsTo('playlistItems')[0]['params']['maxResults']);

        Event::assertDispatched(ChannelSynced::class, fn ($e) => $e->channel->is($channel) && $e->created === 2 && $e->updated === 0);
    }

    public function test_second_sync_updates_without_duplicates(): void
    {
        $channel = $this->channel();
        array_push(
            $this->fake->responses,
            self::playlistPage(['v1']),
            self::videoList([self::video('v1')]),
            self::playlistPage(['v1']),
            self::videoList([self::video('v1', ['viewCount' => '20'])]),
        );

        $this->sync($channel);
        $this->sync($channel);

        $this->assertSame(1, YoutubeVideo::count());
        $this->assertSame(20, YoutubeVideo::first()->view_count);
        Event::assertDispatched(ChannelSynced::class, fn ($e) => $e->created === 0 && $e->updated === 1);
    }

    public function test_missing_statistics_member_stores_null(): void
    {
        $channel = $this->channel();
        array_push(
            $this->fake->responses,
            self::playlistPage(['v1']),
            self::videoList([self::video('v1', ['viewCount' => '10', 'commentCount' => '0'])]),
        );

        $this->sync($channel);

        $video = YoutubeVideo::first();
        $this->assertNull($video->like_count);
        $this->assertSame(0, $video->comment_count);
    }

    public function test_thumbnail_falls_back_to_default(): void
    {
        $channel = $this->channel();
        $video = self::video('v1');
        unset($video['snippet']['thumbnails']['high']);
        array_push($this->fake->responses, self::playlistPage(['v1']), self::videoList([$video]));

        $this->sync($channel);

        $this->assertSame('https://i.ytimg.com/vi/v1/default.jpg', YoutubeVideo::first()->thumbnail_url);
    }

    public function test_max_pages_limits_playlist_requests(): void
    {
        $pages = [
            self::playlistPage(['v1'], 'P2'),
            self::playlistPage(['v2'], 'P3'),
            self::playlistPage(['v3']),
        ];

        array_push($this->fake->responses, ...$pages);
        $this->sync($this->channel(), 2);

        $playlistRequests = $this->requestsTo('playlistItems');
        $this->assertCount(2, $playlistRequests);
        $this->assertSame(['', 'P2'], array_column(array_column($playlistRequests, 'params'), 'pageToken'));
        $this->assertSame('v1,v2', $this->requestsTo('videos')[0]['params']['id']);
    }

    public function test_null_max_pages_reads_every_page(): void
    {
        array_push(
            $this->fake->responses,
            self::playlistPage(['v1'], 'P2'),
            self::playlistPage(['v2'], 'P3'),
            self::playlistPage(['v3']),
        );

        $this->sync($this->channel(), null);

        $this->assertCount(3, $this->requestsTo('playlistItems'));
        $this->assertSame('v1,v2,v3', $this->requestsTo('videos')[0]['params']['id']);
    }

    public function test_check_set_includes_known_videos_in_batches_of_50(): void
    {
        $channel = $this->channel();
        foreach (range(1, 60) as $n) {
            $channel->videos()->create(['video_id' => "known$n", 'title' => 'Known', 'published_at' => '2026-01-01 00:00:00']);
        }
        $this->fake->responses[] = self::playlistPage(['new1', 'known1']);

        $this->sync($channel);

        $batches = array_map(fn ($request) => explode(',', $request['params']['id']), $this->requestsTo('videos'));
        $this->assertCount(2, $batches);
        $this->assertCount(50, $batches[0]);
        $this->assertCount(11, $batches[1]);

        $checked = array_merge(...$batches);
        sort($checked);
        $expected = array_merge(['new1'], array_map(fn ($n) => "known$n", range(1, 60)));
        sort($expected);
        $this->assertSame($expected, $checked);
    }

    public function test_null_uploads_playlist_is_filled_before_reading(): void
    {
        $channel = $this->channel(['uploads_playlist_id' => null, 'title' => self::CHANNEL_ID]);
        array_push(
            $this->fake->responses,
            json_encode(['kind' => 'youtube#channelListResponse', 'etag' => 'e', 'items' => [[
                'kind' => 'youtube#channel',
                'id' => self::CHANNEL_ID,
                'snippet' => ['title' => 'XDA', 'customUrl' => '@xda'],
                'contentDetails' => ['relatedPlaylists' => ['uploads' => self::UPLOADS]],
            ]]]),
            self::playlistPage(['v1']),
            self::videoList([self::video('v1')]),
        );

        $this->sync($channel);

        $channel->refresh();
        $this->assertSame(self::UPLOADS, $channel->uploads_playlist_id);
        $this->assertSame('@xda', $channel->handle);
        $this->assertSame('XDA', $channel->title);
        $this->assertSame(self::CHANNEL_ID, $this->requestsTo('channels')[0]['params']['id']);
        $this->assertSame(self::UPLOADS, $this->requestsTo('playlistItems')[0]['params']['playlistId']);
        $this->assertSame('ok', $channel->sync_status);
    }

    public function test_channel_lookup_without_item_records_channel_not_found(): void
    {
        $channel = $this->channel(['uploads_playlist_id' => null]);
        $this->fake->responses[] = '{"kind":"youtube#channelListResponse","etag":"e","items":[]}';

        $this->sync($channel);

        $error = $channel->syncErrors()->sole();
        $this->assertSame('channel_not_found', $error->reason);
        $this->assertNull($error->http_code);
        $this->assertSame('Channel not found : '.self::CHANNEL_ID, $error->message);
        $this->assertCount(1, $this->fake->requests);

        $channel->refresh();
        $this->assertSame('failed', $channel->sync_status);
        $this->assertSame('channel_not_found', $channel->last_error_reason);
        Event::assertDispatched(ChannelSyncFailed::class, fn ($e) => $e->reason === 'channel_not_found');
        Event::assertNotDispatched(ChannelSynced::class);
    }

    public function test_api_error_is_recorded_and_not_rethrown(): void
    {
        $channel = $this->channel();
        $channel->update(['last_synced_at' => '2026-10-01 00:00:00']);
        $this->fake->responses[] = self::errorBody(400, 'API key not valid. Please pass a valid API key.', 'badRequest');

        $this->sync($channel);

        $error = $channel->syncErrors()->sole();
        $this->assertSame('invalid_key', $error->reason);
        $this->assertSame(400, $error->http_code);
        $this->assertStringContainsString('API key not valid', $error->message);

        $channel->refresh();
        $this->assertSame('failed', $channel->sync_status);
        $this->assertSame('invalid_key', $channel->last_error_reason);
        $this->assertSame('2026-10-01 00:00:00', $channel->last_synced_at->format('Y-m-d H:i:s'));
        Event::assertDispatched(ChannelSyncFailed::class, fn ($e) => $e->channel->is($channel) && $e->reason === 'invalid_key' && $e->exception->getCode() === 400);
        Event::assertNotDispatched(QuotaExceeded::class);
        $this->assertFalse((new QuotaBlock)->isActive());
    }

    public function test_quota_exceeded_starts_a_quota_block(): void
    {
        $channel = $this->channel();
        array_push(
            $this->fake->responses,
            self::playlistPage(['v1']),
            self::errorBody(403, 'The request cannot be completed because you have exceeded your quota.', 'quotaExceeded'),
        );

        $this->sync($channel);

        $this->assertSame('quota_exceeded', $channel->syncErrors()->sole()->reason);
        $this->assertTrue((new QuotaBlock)->isActive());
        Event::assertDispatched(QuotaExceeded::class, fn ($e) => $e->until->equalTo((new QuotaBlock)->until()));
        Event::assertDispatched(ChannelSyncFailed::class, fn ($e) => $e->reason === 'quota_exceeded');
    }

    public function test_records_written_before_a_failure_are_kept(): void
    {
        $channel = $this->channel();
        $channel->videos()->create(['video_id' => 'known1', 'title' => 'Known', 'published_at' => '2026-01-01 00:00:00']);
        $ids = array_map(fn ($n) => "v$n", range(1, 50));
        array_push(
            $this->fake->responses,
            self::playlistPage($ids),
            self::videoList(array_map(fn ($id) => self::video($id), $ids)),
            self::errorBody(500, 'Backend Error', 'backendError'),
        );

        $this->sync($channel);

        $this->assertSame(51, YoutubeVideo::count());
        $this->assertSame('unknown', $channel->syncErrors()->sole()->reason);
    }

    public function test_quota_block_stops_the_job_before_any_request(): void
    {
        $channel = $this->channel();
        (new QuotaBlock)->start();

        $this->sync($channel);

        $this->assertSame([], $this->fake->requests);
        $this->assertNull($channel->refresh()->sync_status);
        $this->assertSame(0, $channel->syncErrors()->count());
        Event::assertNothingDispatched();
    }
}
