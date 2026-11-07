<?php

namespace Cable8mm\Youtube\Support;

class VideoKind
{
    public const SHORTS = 'shorts';

    public const LIVE = 'live';

    public const UPCOMING = 'upcoming';

    public const REGULAR = 'regular';

    /**
     * Classify one videos Resource Object without sending a request.
     *
     * @param  object  $video  One item of a videos.list response.
     * @return 'shorts'|'live'|'upcoming'|'regular'
     */
    public static function of(object $video): string
    {
        if (isset($video->snippet->thumbnails->shortsAspectRatio)) {
            return self::SHORTS;
        }

        $broadcast = $video->snippet->liveBroadcastContent ?? 'none';

        if ($broadcast === 'live') {
            return self::LIVE;
        }

        if ($broadcast === 'upcoming') {
            return self::UPCOMING;
        }

        $seconds = Duration::toSeconds($video->contentDetails->duration ?? null);

        if ($seconds !== null && $seconds <= 60) {
            return self::SHORTS;
        }

        return self::REGULAR;
    }
}
