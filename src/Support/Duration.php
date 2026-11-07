<?php

namespace Cable8mm\Youtube\Support;

use DateInterval;
use Exception;

/**
 * Formats an ISO 8601 duration, as returned in `contentDetails.duration`.
 */
class Duration
{
    /**
     * Total whole seconds of an ISO 8601 duration, or null when it is missing or invalid.
     */
    public static function toSeconds(?string $duration): ?int
    {
        if ($duration === null || $duration === '') {
            return null;
        }

        try {
            $interval = new DateInterval($duration);
        } catch (Exception) {
            return null;
        }

        if ($interval->y !== 0 || $interval->m !== 0) {
            return null;
        }

        return $interval->d * 86400
            + $interval->h * 3600
            + $interval->i * 60
            + $interval->s;
    }

    /**
     * Format a duration as `M:SS`, or `H:MM:SS` when it lasts an hour or more.
     */
    public static function format(?string $duration): ?string
    {
        if ($duration === null || $duration === '') {
            return null;
        }

        $total = self::toSeconds($duration);

        if ($total === null) {
            return null;
        }

        $hours = intdiv($total, 3600);
        $minutes = intdiv($total % 3600, 60);
        $seconds = $total % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%d:%02d', $minutes, $seconds);
    }
}
