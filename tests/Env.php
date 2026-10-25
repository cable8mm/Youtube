<?php

namespace Cable8mm\Youtube\Tests;

use Dotenv\Dotenv;

/**
 * Reads test settings from .env once per process.
 *
 * Dotenv::load() only returns the variables it set itself, so with an
 * immutable repository every test class after the first one (and any value
 * already set by phpunit.xml.dist) would see nothing. Read the environment
 * after loading instead.
 */
final class Env
{
    private static bool $loaded = false;

    public static function get(string $key): ?string
    {
        if (! self::$loaded) {
            Dotenv::createImmutable(__DIR__.'/../')->safeLoad();
            self::$loaded = true;
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function youtubeEnabled(): bool
    {
        return self::get('YOUTUBE_ENABLED') === 'true';
    }
}
