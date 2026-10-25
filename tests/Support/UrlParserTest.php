<?php

namespace Cable8mm\Youtube\Tests\Support;

use Cable8mm\Youtube\Support\UrlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UrlParserTest extends TestCase
{
    public static function invalidVideoUrls(): array
    {
        return [
            'youtube.com without query' => ['https://www.youtube.com/'],
            'youtube.com without host path' => ['https://www.youtube.com'],
            'empty v param' => ['https://www.youtube.com/watch?v='],
            'array v param' => ['https://www.youtube.com/watch?v[]=abc'],
            'embed without id' => ['https://www.youtube.com/embed/'],
            'youtu.be without path' => ['https://youtu.be'],
            'youtu.be root path' => ['https://youtu.be/'],
        ];
    }

    #[DataProvider('invalidVideoUrls')]
    public function test_video_id_throws_for_urls_without_id(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The supplied URL does not look like a Youtube URL');

        UrlParser::videoId($url);
    }

    public function test_video_id_reads_v_param_among_others(): void
    {
        $this->assertSame('rie-hPVJ7Sw', UrlParser::videoId('https://www.youtube.com/watch?t=12&v=rie-hPVJ7Sw&list=PL1'));
    }

    public function test_video_id_url_decodes_v_param(): void
    {
        $this->assertSame('rie-hPVJ7Sw', UrlParser::videoId('https://www.youtube.com/watch?v=rie%2DhPVJ7Sw'));
    }

    public function test_query_returns_empty_array_without_query(): void
    {
        $this->assertSame([], UrlParser::query('https://www.youtube.com/watch'));
    }

    public function test_path_returns_empty_string_without_path(): void
    {
        $this->assertSame('', UrlParser::path('https://youtu.be'));
    }

    public function test_channel_throws_for_bare_youtube_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not look like a Youtube Channel URL');

        UrlParser::channel('https://www.youtube.com');
    }
}
