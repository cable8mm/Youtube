<?php

namespace Cable8mm\Youtube\Sync;

use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository;

/**
 * Stops every Sync from sending requests until the daily API quota resets
 * (PRODUCT_SPEC.md §19.3).
 *
 * Without a bound cache store a Quota Block is never active.
 */
class QuotaBlock
{
    public const CACHE_KEY = 'youtube_quota_blocked_until';

    /** The YouTube Data API daily quota resets at midnight in this time zone. */
    public const RESET_TIMEZONE = 'America/Los_Angeles';

    public function __construct(protected ?Repository $cache = null)
    {
        if ($this->cache === null && function_exists('app') && app()->bound('cache')) {
            $this->cache = app('cache')->store();
        }
    }

    /**
     * Start a block that lasts until the next quota reset, and return its end.
     */
    public function start(): Carbon
    {
        $until = Carbon::now(self::RESET_TIMEZONE)->addDay()->startOfDay();

        $this->cache?->put(self::CACHE_KEY, $until->toIso8601String(), $until);

        return $until;
    }

    /**
     * The end of the active block, or null when there is none.
     */
    public function until(): ?Carbon
    {
        $value = $this->cache?->get(self::CACHE_KEY);

        if (! is_string($value)) {
            return null;
        }

        $until = Carbon::parse($value)->setTimezone(self::RESET_TIMEZONE);

        return Carbon::now()->lt($until) ? $until : null;
    }

    public function isActive(): bool
    {
        return $this->until() !== null;
    }

    public function clear(): void
    {
        $this->cache?->forget(self::CACHE_KEY);
    }
}
