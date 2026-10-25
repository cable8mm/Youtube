<?php

namespace Cable8mm\Youtube\Sync;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;
use Cable8mm\Youtube\Support\UrlParser;
use Cable8mm\Youtube\Youtube;

/**
 * Resolves one import cell to a channels Resource Object (PRODUCT_SPEC.md §17.2).
 *
 * Sends at most one channels request and never a search request.
 */
class ChannelInputResolver
{
    public const PART = ['id', 'snippet', 'contentDetails'];

    public const UNSUPPORTED = 'unsupported input';

    public const NOT_FOUND = 'channel not found';

    public function __construct(protected ?Youtube $youtube = null) {}

    /**
     * Return the channel, or the failure cause (UNSUPPORTED or NOT_FOUND).
     *
     * @throws YoutubeApiException
     */
    public function resolve(?string $cell): \stdClass|string
    {
        $lookup = static::lookup(trim((string) $cell));

        if ($lookup === null) {
            return self::UNSUPPORTED;
        }

        [$method, $value] = $lookup;
        $youtube = $this->youtube ?? app(Youtube::class);

        return $youtube->{$method}($value, [], self::PART) ?? self::NOT_FOUND;
    }

    /**
     * Decide which lookup a cell needs, without sending a request.
     *
     * @return array{0: 'getChannelById'|'getChannelByHandle'|'getChannelByName', 1: string}|null
     */
    public static function lookup(string $input): ?array
    {
        if (preg_match('/^UC[A-Za-z0-9_-]{22}$/', $input)) {
            return ['getChannelById', $input];
        }

        if (str_starts_with($input, '@')) {
            return strlen($input) > 1 ? ['getChannelByHandle', $input] : null;
        }

        $host = parse_url($input, PHP_URL_HOST);

        if (! is_string($host) || ! ($host === 'youtube.com' || str_ends_with($host, '.youtube.com'))) {
            return null;
        }

        $segments = explode('/', trim(UrlParser::path($input), '/'));

        return match (true) {
            $segments[0] === 'channel' && ! empty($segments[1]) => ['getChannelById', $segments[1]],
            str_starts_with($segments[0], '@') && strlen($segments[0]) > 1 => ['getChannelByHandle', $segments[0]],
            $segments[0] === 'user' && ! empty($segments[1]) => ['getChannelByName', $segments[1]],
            default => null,
        };
    }
}
