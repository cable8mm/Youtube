<?php

namespace Cable8mm\Youtube\Concerns;

use Cable8mm\Youtube\Models\YoutubeChannel;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Use on a host model that owns YouTube channels.
 */
trait HasYoutubeChannels
{
    /**
     * @return MorphMany<YoutubeChannel, $this>
     */
    public function youtubeChannels(): MorphMany
    {
        return $this->morphMany(YoutubeChannel::class, 'owner');
    }
}
