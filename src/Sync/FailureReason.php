<?php

namespace Cable8mm\Youtube\Sync;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;
use Throwable;

/**
 * Maps a sync exception to a Failure Reason (PRODUCT_SPEC.md §19.1).
 */
final class FailureReason
{
    public const QUOTA_EXCEEDED = 'quota_exceeded';

    public const INVALID_KEY = 'invalid_key';

    public const FORBIDDEN = 'forbidden';

    public const CHANNEL_NOT_FOUND = 'channel_not_found';

    public const TRANSPORT = 'transport';

    public const UNKNOWN = 'unknown';

    private const QUOTA_REASONS = ['quotaExceeded', 'dailyLimitExceeded', 'rateLimitExceeded'];

    private const NOT_FOUND_REASONS = ['playlistNotFound', 'channelNotFound'];

    private const TRANSPORT_PREFIXES = ['Curl Error : ', 'Invalid response from Youtube API : '];

    /**
     * Return the Failure Reason of an exception. The first matching rule wins.
     */
    public static function fromThrowable(Throwable $e): string
    {
        if (! $e instanceof YoutubeApiException) {
            return self::UNKNOWN;
        }

        $message = $e->getMessage();
        $reason = self::apiReason($message);

        return match (true) {
            in_array($reason, self::QUOTA_REASONS, true) => self::QUOTA_EXCEEDED,
            $e->getCode() === 400 && str_contains($message, 'API key not valid') => self::INVALID_KEY,
            $e->getCode() === 403 => self::FORBIDDEN,
            in_array($reason, self::NOT_FOUND_REASONS, true) => self::CHANNEL_NOT_FOUND,
            self::isTransport($message) => self::TRANSPORT,
            default => self::UNKNOWN,
        };
    }

    /**
     * The `error.errors[0].reason` that YoutubeApiException::fromApiError() appends as " : <reason>".
     */
    private static function apiReason(string $message): ?string
    {
        if (! str_starts_with($message, 'Error ')) {
            return null;
        }

        return preg_match('/ : (\w+)$/', $message, $matches) === 1 ? $matches[1] : null;
    }

    private static function isTransport(string $message): bool
    {
        foreach (self::TRANSPORT_PREFIXES as $prefix) {
            if (str_starts_with($message, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
