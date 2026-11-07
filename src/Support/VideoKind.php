<?php

namespace Cable8mm\Youtube\Support;

use Carbon\Carbon;
use Throwable;

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

        if ($seconds !== null && $seconds <= 180 && self::publishedOnOrAfterThreeMinuteShorts($video->snippet->publishedAt ?? null)) {
            return self::SHORTS;
        }

        return self::REGULAR;
    }

    /**
     * Three-minute Shorts started on 2024-10-15; older uploads stay long-form.
     */
    private static function publishedOnOrAfterThreeMinuteShorts(?string $publishedAt): bool
    {
        if ($publishedAt === null || $publishedAt === '') {
            return false;
        }

        try {
            $published = Carbon::parse($publishedAt);
        } catch (Throwable) {
            return false;
        }

        return $published->greaterThanOrEqualTo(Carbon::parse('2024-10-15T00:00:00Z'));
    }
}
