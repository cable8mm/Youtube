<?php

namespace Cable8mm\Youtube\Tests;

use Cable8mm\Youtube\Support\UrlParser;
use Cable8mm\Youtube\Tests\Resources\FakeApiClient;
use Cable8mm\Youtube\Youtube;
use PHPUnit\Framework\TestCase;

class YoutubeTest extends TestCase
{
    // ==================== Unit Tests with Mocking ====================

    public function test_constructor_requires_api_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Google API key is Required');

        new Youtube('');
    }

    public function test_constructor_accepts_valid_api_key(): void
    {
        $youtube = new Youtube('valid_api_key');

        $this->assertInstanceOf(Youtube::class, $youtube);
        $this->assertEquals('valid_api_key', $youtube->getApiKey());
    }

    public function test_use_http_host_sets_config_correctly(): void
    {
        $youtube = new Youtube('test_key');
        $youtube->useHttpHost(true);

        $this->assertTrue($youtube->getClient()->isUsingHttpHost());
    }

    public function test_set_api_key_updates_key(): void
    {
        $youtube = new Youtube('old_key');
        $youtube->setApiKey('new_key');

        $this->assertEquals('new_key', $youtube->getApiKey());
    }

    public function test_fluent_interface_works(): void
    {
        $youtube = (new Youtube('test_key'))
            ->useHttpHost(true)
            ->setApiKey('another_key')
            ->cache()
            ->setCacheTtl(1800);

        $this->assertEquals('another_key', $youtube->getApiKey());
    }

    public function test_parse_vid_from_ur_l_with_standard_url(): void
    {
        $videoId = Youtube::parseVidFromURL('https://www.youtube.com/watch?v=rie-hPVJ7Sw');

        $this->assertEquals('rie-hPVJ7Sw', $videoId);
    }

    public function test_parse_vid_from_ur_l_with_short_url(): void
    {
        $videoId = Youtube::parseVidFromURL('https://youtu.be/rie-hPVJ7Sw');

        $this->assertEquals('rie-hPVJ7Sw', $videoId);
    }

    public function test_parse_vid_from_ur_l_with_embed_url(): void
    {
        $videoId = Youtube::parseVidFromURL('https://www.youtube.com/embed/rie-hPVJ7Sw');

        $this->assertEquals('rie-hPVJ7Sw', $videoId);
    }

    public function test_parse_vid_from_ur_l_throws_exception_for_invalid_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The supplied URL does not look like a Youtube URL');

        Youtube::parseVidFromURL('https://example.com');
    }

    public function test_cache_method_enables_caching(): void
    {
        $youtube = new Youtube('test_key', ['cache_enabled' => false]);
        $youtube->cache(true);

        $this->assertTrue($youtube->getClient()->isCacheEnabled());
    }

    public function test_set_cache_ttl_sets_ttl_correctly(): void
    {
        $youtube = new Youtube('test_key');
        $youtube->setCacheTtl(1800);

        $this->assertEquals(1800, $youtube->getClient()->getCacheTtl());
    }

    public function test_search_advanced_throws_exception_without_params(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $youtube = new Youtube('test_key');
        $youtube->searchAdvanced([]);
    }

    public function test_get_activities_by_channel_id_throws_exception_without_channel_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ChannelId must be supplied');

        $youtube = new Youtube('test_key');
        $youtube->getActivitiesByChannelId('');
    }

    public function test_constructor_config_sets_client_settings(): void
    {
        $client = (new Youtube('k', [
            'use-http-host' => true,
            'cache_enabled' => true,
            'cache_ttl' => 60,
        ]))->getClient();

        $this->assertTrue($client->isUsingHttpHost());
        $this->assertTrue($client->isCacheEnabled());
        $this->assertSame(60, $client->getCacheTtl());
    }

    // ==================== Low-level Helper Tests ====================

    public function test_get_api_returns_endpoint_url(): void
    {
        $this->assertSame('https://www.googleapis.com/youtube/v3/videos', (new Youtube('test_key'))->getApi('videos.list'));
    }

    public function test_decode_helpers_apply_result_rules_to_body_with_items(): void
    {
        $youtube = new Youtube('test_key');
        $body = '{"kind":"k","etag":"e","items":[{"id":"a"},{"id":"b"}]}';

        $this->assertSame('a', $youtube->decodeSingle($body)->id);
        $this->assertSame(['a', 'b'], array_column($youtube->decodeMultiple($body), 'id'));
        $this->assertSame(['a', 'b'], array_column($youtube->decodeList($body), 'id'));
    }

    public function test_decode_helpers_apply_result_rules_to_body_without_items(): void
    {
        $youtube = new Youtube('test_key');
        $body = '{"kind":"k","etag":"e","items":[]}';

        $this->assertNull($youtube->decodeSingle($body));
        $this->assertNull($youtube->decodeMultiple($body));
        $this->assertFalse($youtube->decodeList($body));
    }

    public function test_api_get_returns_raw_body_and_sends_api_key(): void
    {
        $fake = new FakeApiClient(['{"items":[]}']);
        $youtube = new class('test_key') extends Youtube
        {
            public function useClient(FakeApiClient $client): void
            {
                $this->client = $client;
            }
        };
        $youtube->useClient($fake);

        $url = 'https://www.googleapis.com/youtube/v3/videos';

        $this->assertSame('{"items":[]}', $youtube->api_get($url, ['id' => 'a']));
        $this->assertSame($url, $fake->requests[0]['url']);
        $this->assertSame(['id' => 'a', 'key' => 'test_key'], $fake->requests[0]['params']);
    }

    public function test_parse_url_helpers_match_url_parser(): void
    {
        $url = 'https://www.youtube.com/watch?v=abc';

        $this->assertSame(UrlParser::path($url), Youtube::_parse_url_path($url));
        $this->assertSame(UrlParser::query($url), Youtube::_parse_url_query($url));
    }

    public function test_parse_vid_from_url(): void
    {
        $videoId = Youtube::parseVidFromURL('https://www.youtube.com/watch?v=moSFlvxnbgk');

        $this->assertEquals('moSFlvxnbgk', $videoId);
    }
}
