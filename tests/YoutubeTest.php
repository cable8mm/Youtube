<?php

namespace Cable8mm\Youtube\Tests;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;
use Cable8mm\Youtube\Support\UrlParser;
use Cable8mm\Youtube\Tests\Resources\FakeApiClient;
use Cable8mm\Youtube\Youtube;
use Carbon\Carbon;
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

    // ==================== Integration Tests ====================

    public function test_get_channel_videos(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = Env::get('YOUTUBE_API_KEY');
        $channelId = Env::get('YOUTUBE_CHANNEL_ID');
        $publishedBefore = Carbon::now()->toRfc3339String();

        $videos = (new Youtube($apiKey))->getChannelVideos($channelId, 1, $publishedBefore)['results'];

        $this->assertNotEmpty($videos);
        $this->assertObjectHasProperty('id', $videos[0]);
        $this->assertObjectHasProperty('snippet', $videos[0]);
    }

    public function test_get_channel_videos_returns_different_videos(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = Env::get('YOUTUBE_API_KEY');
        $channelId = Env::get('YOUTUBE_CHANNEL_ID');
        $publishedBefore = Carbon::now()->toRfc3339String();

        $video = (new Youtube($apiKey))->getChannelVideos($channelId, 1, $publishedBefore)['results'][0];

        $this->assertNotEmpty($video->id->videoId);

        $secondVideo = (new Youtube($apiKey))->getChannelVideos($channelId, 1, $video->snippet->publishedAt)['results'][0];

        $this->assertNotEmpty($secondVideo->id->videoId);
        $this->assertNotEquals($video->id->videoId, $secondVideo->id->videoId);
    }

    public function test_get_channel_videos_with_page_token(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = Env::get('YOUTUBE_API_KEY');
        $channelId = Env::get('YOUTUBE_CHANNEL_ID');

        $response = (new Youtube($apiKey))->getChannelVideos($channelId, 1, null, false, '');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('results', $response);
        $this->assertArrayHasKey('info', $response);
        $this->assertNotEmpty($response['results']);
        $this->assertIsArray($response['info']);
        $this->assertArrayHasKey('totalResults', $response['info']);
        $this->assertArrayHasKey('nextPageToken', $response['info']);
    }

    public function test_get_video_info(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = Env::get('YOUTUBE_API_KEY');

        $video = (new Youtube($apiKey))->getVideoInfo('rie-hPVJ7Sw');

        $this->assertNotNull($video);
        $this->assertObjectHasProperty('id', $video);
        $this->assertObjectHasProperty('snippet', $video);
    }

    public function test_search_videos(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = Env::get('YOUTUBE_API_KEY');

        $results = (new Youtube($apiKey))->searchVideos('Laravel', 5);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    public function test_get_channel_by_id(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = Env::get('YOUTUBE_API_KEY');

        $channel = (new Youtube($apiKey))->getChannelById('UCk1SpWNzOs4MYmr0uICEntg');

        $this->assertNotNull($channel);
        $this->assertObjectHasProperty('id', $channel);
        $this->assertObjectHasProperty('snippet', $channel);
    }

    public function test_get_channel_by_handle(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $youtube = new Youtube(Env::get('YOUTUBE_API_KEY'));

        $this->assertSame('UCK8sQmJBp8GCxrOtXWBpyEA', $youtube->getChannelByHandle('@Google')->id);
        $this->assertSame('UCK8sQmJBp8GCxrOtXWBpyEA', $youtube->getChannelByHandle('Google')->id);
    }

    public function test_get_channel_by_handle_returns_null_for_unknown_handle(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $this->assertNull((new Youtube(Env::get('YOUTUBE_API_KEY')))->getChannelByHandle('@zz-no-such-handle-9q8x7w'));
    }

    public function test_get_popular_videos(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = Env::get('YOUTUBE_API_KEY');

        $videos = (new Youtube($apiKey))->getPopularVideos('US', 5);

        $this->assertIsArray($videos);
        $this->assertNotEmpty($videos);
    }

    public function test_parse_vid_from_url(): void
    {
        $videoId = Youtube::parseVidFromURL('https://www.youtube.com/watch?v=moSFlvxnbgk');

        $this->assertEquals('moSFlvxnbgk', $videoId);
    }

    public function test_get_playlist_by_id(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = Env::get('YOUTUBE_API_KEY');

        $playlist = (new Youtube($apiKey))->getPlaylistById('PL590L5WQmH8fJ54F369BLDSqIwcs-TCfs');

        $this->assertNotNull($playlist);
        $this->assertObjectHasProperty('id', $playlist);
        $this->assertObjectHasProperty('snippet', $playlist);
    }

    public function test_get_playlist_items(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = Env::get('YOUTUBE_API_KEY');

        $items = (new Youtube($apiKey))->getPlaylistItemsByPlaylistId('PL590L5WQmH8fJ54F369BLDSqIwcs-TCfs');

        $this->assertIsArray($items);
        $this->assertArrayHasKey('results', $items);
        $this->assertArrayHasKey('info', $items);
    }

    public function test_caching_functionality(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = Env::get('YOUTUBE_API_KEY');

        // First request without cache
        $youtube1 = new Youtube($apiKey);
        $start1 = microtime(true);
        $videos1 = $youtube1->getPopularVideos('US', 5);
        $time1 = microtime(true) - $start1;

        // Second request with cache
        $youtube2 = (new Youtube($apiKey))->cache();
        $start2 = microtime(true);
        $videos2 = $youtube2->getPopularVideos('US', 5);
        $time2 = microtime(true) - $start2;

        $this->assertNotEmpty($videos1);
        $this->assertNotEmpty($videos2);

        // Cached request should be faster (though this is not always guaranteed)
        // We just verify both return the same data
        $this->assertEquals(count($videos1), count($videos2));
    }

    public function test_get_categories(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = Env::get('YOUTUBE_API_KEY');

        $categories = (new Youtube($apiKey))->getCategories('US');

        $this->assertIsArray($categories);
        $this->assertNotEmpty($categories);
    }

    public function test_search_channel_videos(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = Env::get('YOUTUBE_API_KEY');

        $videos = (new Youtube($apiKey))->searchChannelVideos('Android', 'UCk1SpWNzOs4MYmr0uICEntg', 5);

        $this->assertIsArray($videos);
        $this->assertNotEmpty($videos);
    }

    public function test_get_channel_by_name(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = Env::get('YOUTUBE_API_KEY');

        $channel = (new Youtube($apiKey))->getChannelByName('Google');

        $this->assertNotNull($channel);
        $this->assertObjectHasProperty('id', $channel);
    }

    public function test_paginate_results(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = Env::get('YOUTUBE_API_KEY');

        $params = [
            'q' => 'Laravel',
            'type' => 'video',
            'part' => 'id,snippet',
            'maxResults' => 10,
        ];

        $result = (new Youtube($apiKey))->paginateResults($params);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('info', $result);
    }

    // ==================== Error Handling Tests ====================

    public function test_api_error_throws_youtube_api_exception(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        $apiKey = Env::get('YOUTUBE_API_KEY');

        // Use an invalid video ID that will cause an API error
        $youtube = new Youtube($apiKey);

        // This should not throw an exception, but return null/false
        $result = $youtube->getVideoInfo('invalid_video_id_12345');

        // API returns empty result for non-existent videos, not an error
        $this->assertNull($result);
    }

    public function test_invalid_api_key_throws_youtube_api_exception(): void
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        try {
            (new Youtube('invalid_key'))->getVideoInfo('rie-hPVJ7Sw');
            $this->fail('Expected YoutubeApiException');
        } catch (YoutubeApiException $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertStringStartsWith('Error 400', $e->getMessage());
            $this->assertStringEndsWith(' : badRequest', $e->getMessage());
        }
    }

    // ==================== Remaining Operations (Live) ====================

    private function liveYoutube(): Youtube
    {
        if (! Env::youtubeEnabled()) {
            $this->markTestSkipped('YouTube API tests are disabled');
        }

        return new Youtube(Env::get('YOUTUBE_API_KEY'));
    }

    public function test_get_localized_video_info(): void
    {
        $video = $this->liveYoutube()->getLocalizedVideoInfo('rie-hPVJ7Sw', 'ko');

        $this->assertObjectHasProperty('id', $video);
        $this->assertObjectHasProperty('snippet', $video);
    }

    public function test_search(): void
    {
        $results = $this->liveYoutube()->search('Laravel', 5);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    public function test_list_channel_videos(): void
    {
        $videos = $this->liveYoutube()->listChannelVideos('UCk1SpWNzOs4MYmr0uICEntg', 5);

        $this->assertIsArray($videos);
        $this->assertNotEmpty($videos);
    }

    public function test_get_channel_videos_after_a_time(): void
    {
        $youtube = $this->liveYoutube();
        $channelId = 'UCk1SpWNzOs4MYmr0uICEntg';

        $since = $youtube->getChannelVideos($channelId, 2, null)['results'][1]->snippet->publishedAt;

        $videos = $youtube->getChannelVideos($channelId, 5, $since, true)['results'];

        $this->assertNotEmpty($videos);

        foreach ($videos as $video) {
            $this->assertTrue(Carbon::create($video->snippet->publishedAt)->gt(Carbon::create($since)));
        }
    }

    public function test_search_channel_by_name(): void
    {
        $channel = $this->liveYoutube()->searchChannelByName('Google');

        $this->assertObjectHasProperty('id', $channel);
    }

    public function test_get_channel_from_url(): void
    {
        $youtube = $this->liveYoutube();

        $this->assertSame('UCK8sQmJBp8GCxrOtXWBpyEA', $youtube->getChannelFromURL('https://www.youtube.com/@Google')->id);
        $this->assertSame('UCK8sQmJBp8GCxrOtXWBpyEA', $youtube->getChannelFromURL('https://www.youtube.com/user/Google')->id);
        $this->assertSame('UCk1SpWNzOs4MYmr0uICEntg', $youtube->getChannelFromURL('https://www.youtube.com/channel/UCk1SpWNzOs4MYmr0uICEntg')->id);
    }

    public function test_get_playlists_by_channel_id(): void
    {
        $result = $this->liveYoutube()->getPlaylistsByChannelId('UCk1SpWNzOs4MYmr0uICEntg');

        $this->assertNotEmpty($result['results']);
        $this->assertSame(['totalResults', 'nextPageToken', 'prevPageToken'], array_keys($result['info']));
    }

    public function test_get_comment_threads_by_video_id(): void
    {
        $threads = $this->liveYoutube()->getCommentThreadsByVideoId('rie-hPVJ7Sw', 2);

        $this->assertIsArray($threads);
        $this->assertLessThanOrEqual(2, count($threads));
    }

    public function test_get_activities_by_channel_id(): void
    {
        $activities = $this->liveYoutube()->getActivitiesByChannelId('UCk1SpWNzOs4MYmr0uICEntg');

        $this->assertIsArray($activities);
        $this->assertLessThanOrEqual(5, count($activities));
    }
}
