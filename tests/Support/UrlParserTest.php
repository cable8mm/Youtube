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
            'youtube.com in another host query' => ['https://example.com/?ref=youtube.com/watch?v=rie-hPVJ7Sw'],
            'youtu.be in another host path' => ['https://example.com/youtu.be/rie-hPVJ7Sw'],
            'host ending in youtube.com' => ['https://notyoutube.com/watch?v=rie-hPVJ7Sw'],
            'other host' => ['https://example.com'],
        ];
    }

    public static function videoUrls(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v=rie-hPVJ7Sw'],
            'feature=embed param' => ['https://www.youtube.com/watch?v=rie-hPVJ7Sw&feature=embed'],
            'mobile' => ['https://m.youtube.com/watch?v=rie-hPVJ7Sw'],
            'music' => ['https://music.youtube.com/watch?v=rie-hPVJ7Sw'],
            'bare host' => ['https://youtube.com/watch?v=rie-hPVJ7Sw'],
            'upper-case host' => ['https://WWW.YouTube.com/watch?v=rie-hPVJ7Sw'],
            'embed' => ['https://www.youtube.com/embed/rie-hPVJ7Sw'],
            'embed with query' => ['https://www.youtube.com/embed/rie-hPVJ7Sw?start=10'],
            'nocookie embed' => ['https://www.youtube-nocookie.com/embed/rie-hPVJ7Sw'],
            'bare nocookie embed' => ['https://youtube-nocookie.com/embed/rie-hPVJ7Sw'],
            'short' => ['https://youtu.be/rie-hPVJ7Sw'],
            'short with query' => ['https://youtu.be/rie-hPVJ7Sw?t=10'],
            'without scheme' => ['youtube.com/watch?v=rie-hPVJ7Sw'],
            'short without scheme' => ['youtu.be/rie-hPVJ7Sw'],
        ];
    }

    #[DataProvider('videoUrls')]
    public function test_video_id_reads_video_urls(string $url): void
    {
        $this->assertSame('rie-hPVJ7Sw', UrlParser::videoId($url));
    }

    public function test_segments_skip_empty_parts(): void
    {
        $this->assertSame(['channel', 'UC1'], UrlParser::segments('https://www.youtube.com//channel/UC1/'));
        $this->assertSame([], UrlParser::segments('https://www.youtube.com'));
    }

    public function test_host_is_lowercased(): void
    {
        $this->assertSame('www.youtube.com', UrlParser::host('https://WWW.YouTube.com/watch'));
        $this->assertSame('', UrlParser::host('not a url'));
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

    public static function channelUrls(): array
    {
        return [
            'channel' => ['https://www.youtube.com/channel/UC1', ['type' => 'id', 'value' => 'UC1']],
            'channel trailing slash' => ['https://www.youtube.com/channel/UC1/', ['type' => 'id', 'value' => 'UC1']],
            'channel sub-page' => ['https://www.youtube.com/channel/UC1/videos', ['type' => 'id', 'value' => 'UC1']],
            'user sub-page' => ['https://www.youtube.com/user/google/featured', ['type' => 'username', 'value' => 'google']],
            'c sub-page' => ['https://www.youtube.com/c/name/about', ['type' => 'search', 'value' => 'name']],
            'handle' => ['https://www.youtube.com/@xda', ['type' => 'handle', 'value' => '@xda']],
            'handle sub-page' => ['https://www.youtube.com/@xda/videos', ['type' => 'handle', 'value' => '@xda']],
            'mobile handle' => ['https://m.youtube.com/@xda', ['type' => 'handle', 'value' => '@xda']],
            'without scheme' => ['youtube.com/@xda', ['type' => 'handle', 'value' => '@xda']],
        ];
    }

    #[DataProvider('channelUrls')]
    public function test_channel_reads_the_right_segment(string $url, array $expected): void
    {
        $this->assertSame($expected, UrlParser::channel($url));
    }

    public static function channelUrlsWithoutValue(): array
    {
        return [
            'channel' => ['https://www.youtube.com/channel/'],
            'user' => ['https://www.youtube.com/user/'],
            'c' => ['https://www.youtube.com/c'],
        ];
    }

    #[DataProvider('channelUrlsWithoutValue')]
    public function test_channel_prefix_without_value_throws(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not look like a Youtube Channel URL');

        UrlParser::channel($url);
    }

    public static function nonChannelHosts(): array
    {
        return [
            'music' => ['https://music.youtube.com/channel/UC1'],
            'youtube.com in another host query' => ['https://example.com/?u=youtube.com/channel/UC1'],
            'youtu.be' => ['https://youtu.be/channel/UC1'],
        ];
    }

    #[DataProvider('nonChannelHosts')]
    public function test_channel_rejects_other_hosts(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The supplied URL does not look like a Youtube URL');

        UrlParser::channel($url);
    }

    public function test_channel_throws_for_bare_youtube_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not look like a Youtube Channel URL');

        UrlParser::channel('https://www.youtube.com');
    }
}
