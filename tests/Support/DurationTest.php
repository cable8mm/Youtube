<?php

namespace Cable8mm\Youtube\Tests\Support;

use Cable8mm\Youtube\Support\Duration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DurationTest extends TestCase
{
    public static function durations(): array
    {
        return [
            'seconds' => ['PT53S', '0:53'],
            'one minute' => ['PT1M', '1:00'],
            'minutes and seconds' => ['PT9M43S', '9:43'],
            'sixty minutes' => ['PT60M', '1:00:00'],
            'one hour' => ['PT1H', '1:00:00'],
            'hours minutes seconds' => ['PT1H2M10S', '1:02:10'],
            'zero' => ['PT0S', '0:00'],
        ];
    }

    #[DataProvider('durations')]
    public function test_it_formats_an_iso_8601_duration(string $duration, string $expected): void
    {
        $this->assertSame($expected, Duration::format($duration));
    }

    public function test_it_returns_null_for_a_missing_or_invalid_duration(): void
    {
        $this->assertNull(Duration::format(null));
        $this->assertNull(Duration::format(''));
        $this->assertNull(Duration::format('4 minutes 13 seconds'));
    }
}
