<?php

namespace Cable8mm\Youtube\Console;

use Cable8mm\Youtube\Jobs\SyncChannelVideos;
use Cable8mm\Youtube\Models\YoutubeChannel;
use Cable8mm\Youtube\Sync\QuotaBlock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class SyncChannelsCommand extends Command
{
    protected $signature = 'youtube:sync
        {--channel=* : Channel ID to sync, even when inactive}
        {--full : Read every page of the uploads playlist}';

    protected $description = 'Queue a video sync for each YouTube channel';

    public function handle(): int
    {
        $quotaBlock = new QuotaBlock;

        if ($quotaBlock->isActive()) {
            $this->line('quota blocked until '.$quotaBlock->until()->toIso8601String());

            return self::SUCCESS;
        }

        $channelIds = $this->option('channel');

        if ($channelIds === []) {
            $channels = YoutubeChannel::where('is_active', true)->orderBy('id')->get();
        } else {
            $found = YoutubeChannel::whereIn('channel_id', $channelIds)->get()->keyBy('channel_id');
            $channels = [];

            foreach ($channelIds as $channelId) {
                if (isset($found[$channelId])) {
                    $channels[] = $found[$channelId];
                } else {
                    $this->line("unknown channel {$channelId}");
                }
            }
        }

        $maxPages = $this->option('full') ? null : (int) config('youtube.sync.max_pages');
        $queue = config('youtube.sync.queue');

        foreach ($channels as $channel) {
            Bus::dispatch((new SyncChannelVideos($channel->id, $maxPages))->onQueue($queue));
        }

        return self::SUCCESS;
    }
}
