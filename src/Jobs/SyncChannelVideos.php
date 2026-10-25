<?php

namespace Cable8mm\Youtube\Jobs;

use Cable8mm\Youtube\Events\ChannelSynced;
use Cable8mm\Youtube\Events\ChannelSyncFailed;
use Cable8mm\Youtube\Events\QuotaExceeded;
use Cable8mm\Youtube\Exceptions\YoutubeApiException;
use Cable8mm\Youtube\Models\YoutubeChannel;
use Cable8mm\Youtube\Models\YoutubeSyncError;
use Cable8mm\Youtube\Models\YoutubeVideo;
use Cable8mm\Youtube\Sync\FailureReason;
use Cable8mm\Youtube\Sync\QuotaBlock;
use Cable8mm\Youtube\Youtube;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * One Sync of one Channel Record (PRODUCT_SPEC.md §18.2).
 */
class SyncChannelVideos implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public const CHANNEL_PART = ['id', 'snippet', 'contentDetails'];

    public const VIDEO_PART = ['id', 'snippet', 'contentDetails', 'statistics'];

    public const PAGE_SIZE = 50;

    public int $tries = 1;

    /**
     * Set when the channel lookup returns no item, so the failure is recorded as channel_not_found.
     */
    protected bool $channelNotFound = false;

    public function __construct(
        public int $youtubeChannelId,
        public ?int $maxPages = 1,
    ) {}

    public function handle(): void
    {
        try {
            $quotaBlock = new QuotaBlock;

            if ($quotaBlock->isActive()) {
                return;
            }

            $channel = YoutubeChannel::find($this->youtubeChannelId);

            if ($channel === null) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        try {
            $this->sync($channel);
        } catch (Throwable $e) {
            try {
                $this->recordFailure($channel, $quotaBlock, $e);
            } catch (Throwable) {
                // Recording failed too; never let an exception leave the job.
            }
        }
    }

    /**
     * @throws Throwable
     */
    protected function sync(YoutubeChannel $channel): void
    {
        $youtube = app(Youtube::class);

        if ($channel->uploads_playlist_id === null) {
            $this->fillChannel($youtube, $channel);
        }

        $checkSet = array_values(array_unique(array_merge(
            $this->uploadedVideoIds($youtube, $channel->uploads_playlist_id),
            $channel->videos()->pluck('video_id')->all(),
        )));

        $created = 0;
        $updated = 0;

        foreach (array_chunk($checkSet, self::PAGE_SIZE) as $batch) {
            foreach ($youtube->getVideoInfo($batch, self::VIDEO_PART) ?? [] as $item) {
                $this->storeVideo($channel, $item) ? $created++ : $updated++;
            }
        }

        $channel->update([
            'sync_status' => 'ok',
            'last_synced_at' => Carbon::now(),
            'last_error_reason' => null,
        ]);

        Event::dispatch(new ChannelSynced($channel, $created, $updated, 0));
    }

    /**
     * @throws YoutubeApiException
     */
    protected function fillChannel(Youtube $youtube, YoutubeChannel $channel): void
    {
        $item = $youtube->getChannelById($channel->channel_id, [], self::CHANNEL_PART);

        if ($item === null) {
            $this->channelNotFound = true;

            throw new YoutubeApiException('Channel not found : '.$channel->channel_id, 0);
        }

        $channel->update([
            'uploads_playlist_id' => $item->contentDetails->relatedPlaylists->uploads ?? null,
            'handle' => $item->snippet->customUrl ?? null,
            'title' => $item->snippet->title ?? $channel->channel_id,
        ]);
    }

    /**
     * Video IDs of the uploads playlist, reading at most $maxPages pages (every page when null).
     *
     * @return string[]
     *
     * @throws YoutubeApiException
     */
    protected function uploadedVideoIds(Youtube $youtube, string $playlistId): array
    {
        $ids = [];
        $pageToken = '';
        $pages = 0;

        do {
            $page = $youtube->getPlaylistItemsByPlaylistId($playlistId, $pageToken, self::PAGE_SIZE);
            $pages++;

            foreach ($page['results'] ?: [] as $item) {
                $ids[] = $item->contentDetails->videoId;
            }

            $pageToken = $page['info']['nextPageToken'] ?: '';
        } while ($pageToken !== '' && ($this->maxPages === null || $pages < $this->maxPages));

        return $ids;
    }

    /**
     * Create or update the Video Record of a videos Resource Object. Returns true when created.
     */
    protected function storeVideo(YoutubeChannel $channel, object $item): bool
    {
        $video = YoutubeVideo::firstOrNew(['video_id' => $item->id]);
        $created = ! $video->exists;

        $video->fill([
            'youtube_channel_id' => $channel->id,
            'title' => $item->snippet->title,
            'description' => $item->snippet->description ?? null,
            'published_at' => Carbon::parse($item->snippet->publishedAt),
            'duration' => $item->contentDetails->duration ?? null,
            'thumbnail_url' => $item->snippet->thumbnails->high->url ?? $item->snippet->thumbnails->default->url ?? null,
            'view_count' => $item->statistics->viewCount ?? null,
            'like_count' => $item->statistics->likeCount ?? null,
            'comment_count' => $item->statistics->commentCount ?? null,
            'unavailable_at' => null,
        ])->save();

        return $created;
    }

    /**
     * Record a failed Sync (PRODUCT_SPEC.md §19.2).
     */
    protected function recordFailure(YoutubeChannel $channel, QuotaBlock $quotaBlock, Throwable $e): void
    {
        $reason = $this->channelNotFound ? FailureReason::CHANNEL_NOT_FOUND : FailureReason::fromThrowable($e);

        YoutubeSyncError::create([
            'youtube_channel_id' => $channel->id,
            'reason' => $reason,
            'http_code' => $e->getCode() === 0 ? null : (int) $e->getCode(),
            'message' => $e->getMessage(),
        ]);

        $channel->update([
            'sync_status' => 'failed',
            'last_error_reason' => $reason,
        ]);

        if ($reason === FailureReason::QUOTA_EXCEEDED) {
            Event::dispatch(new QuotaExceeded($quotaBlock->start()));
        }

        Event::dispatch(new ChannelSyncFailed($channel, $reason, $e));
    }
}
