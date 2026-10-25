<?php

namespace Cable8mm\Youtube\Events;

use Cable8mm\Youtube\Models\YoutubeChannel;

/**
 * A Sync of a channel finished.
 */
class ChannelSynced
{
    public function __construct(
        public YoutubeChannel $channel,
        public int $created,
        public int $updated,
        public int $unavailable,
    ) {}
}
