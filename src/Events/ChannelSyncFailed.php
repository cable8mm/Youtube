<?php

namespace Cable8mm\Youtube\Events;

use Cable8mm\Youtube\Models\YoutubeChannel;
use Throwable;

/**
 * A Sync of a channel failed and was recorded.
 */
class ChannelSyncFailed
{
    public function __construct(
        public YoutubeChannel $channel,
        public string $reason,
        public Throwable $exception,
    ) {}
}
