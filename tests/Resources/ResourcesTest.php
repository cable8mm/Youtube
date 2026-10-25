<?php

namespace Cable8mm\Youtube\Tests\Resources;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;
use Cable8mm\Youtube\Http\ApiClient;
use Cable8mm\Youtube\Http\ResponseDecoder;
use Cable8mm\Youtube\Resources\Channels;
use Cable8mm\Youtube\Resources\Playlists;
use Cable8mm\Youtube\Resources\Search;
use Cable8mm\Youtube\Resources\Videos;
use Cable8mm\Youtube\Support\UrlParser;
use PHPUnit\Framework\TestCase;

class ResourcesTest extends TestCase
{
    private function listJson(array $items, array $extra = []): string
    {
        return json_encode(['kind' => 'youtube#list', 'etag' => 'etag', 'items' => $items] + $extra);
    }

    public function test_video_info_sends_id_and_key_and_returns_single_item(): void
    {
        $client = new FakeApiClient([$this->listJson([['id' => 'abc']])]);

        $video = (new Videos($client, new ResponseDecoder))->info('abc', ['id']);

        $this->assertSame('abc', $video->id);
        $this->assertSame(ApiClient::ENDPOINTS['videos.list'], $client->requests[0]['url']);
        $this->assertSame(['id' => 'abc', 'part' => 'id', 'key' => 'test_key'], $client->requests[0]['params']);
    }

    public function test_video_info_with_array_of_ids_returns_multiple_items(): void
    {
        $client = new FakeApiClient([$this->listJson([['id' => 'a'], ['id' => 'b']])]);

        $videos = (new Videos($client, new ResponseDecoder))->info(['a', 'b']);

        $this->assertCount(2, $videos);
        $this->assertSame('a,b', $client->requests[0]['params']['id']);
    }

    public function test_video_info_returns_null_when_not_found(): void
    {
        $client = new FakeApiClient([$this->listJson([])]);

        $this->assertNull((new Videos($client, new ResponseDecoder))->info('missing'));
    }

    public function test_api_error_throws_exception(): void
    {
        $client = new FakeApiClient([json_encode(['error' => [
            'code' => 403,
            'message' => 'Forbidden',
            'errors' => [['reason' => 'quotaExceeded']],
        ]])]);

        $this->expectException(YoutubeApiException::class);
        $this->expectExceptionMessage('Error 403 Forbidden : quotaExceeded');

        (new Videos($client, new ResponseDecoder))->info('abc');
    }

    public function test_search_with_page_info_returns_results_and_info(): void
    {
        $client = new FakeApiClient([$this->listJson([['id' => 'x']], [
            'nextPageToken' => 'NEXT',
            'pageInfo' => ['resultsPerPage' => 1, 'totalResults' => 10],
        ])]);

        $page = (new Search($client, new ResponseDecoder))->listChannelVideos('UC1', 1, 'date', ['id'], true);

        $this->assertCount(1, $page['results']);
        $this->assertSame('NEXT', $page['info']['nextPageToken']);
        $this->assertNull($page['info']['prevPageToken']);
        $this->assertSame(10, $page['info']['totalResults']);
        $this->assertSame('date', $client->requests[0]['params']['order']);
    }

    public function test_search_without_results_returns_false(): void
    {
        $client = new FakeApiClient([$this->listJson([])]);

        $this->assertFalse((new Search($client, new ResponseDecoder))->query('nothing'));
    }

    public function test_playlists_by_channel_id_normalizes_page_info(): void
    {
        $client = new FakeApiClient([$this->listJson([['id' => 'p']])]);

        $result = (new Playlists($client, new ResponseDecoder))->byChannelId('UC1');

        $this->assertSame([
            'totalResults' => 0,
            'nextPageToken' => false,
            'prevPageToken' => false,
        ], $result['info']);
    }

    public function test_channel_from_handle_url_searches_then_fetches_by_id(): void
    {
        $client = new FakeApiClient([
            $this->listJson([['snippet' => ['channelId' => 'UC123']]]),
            $this->listJson([['id' => 'UC123']]),
        ]);
        $decoder = new ResponseDecoder;
        $channels = new Channels($client, $decoder, new Search($client, $decoder));

        $channel = $channels->fromUrl('https://www.youtube.com/@laravelphp');

        $this->assertSame('UC123', $channel->id);
        $this->assertSame('laravelphp', $client->requests[0]['params']['q']);
        $this->assertSame('UC123', $client->requests[1]['params']['id']);
    }

    public function test_channel_by_handle_sends_for_handle_and_default_part(): void
    {
        $client = new FakeApiClient([$this->listJson([['id' => 'UC123']])]);
        $decoder = new ResponseDecoder;
        $channels = new Channels($client, $decoder, new Search($client, $decoder));

        $channel = $channels->byHandle('@Google');

        $this->assertSame('UC123', $channel->id);
        $this->assertSame(ApiClient::ENDPOINTS['channels.list'], $client->requests[0]['url']);
        $this->assertSame([
            'forHandle' => '@Google',
            'part' => 'id,snippet,contentDetails,statistics',
            'key' => 'test_key',
        ], $client->requests[0]['params']);
    }

    public function test_channel_by_handle_optional_params_replace_generated_values(): void
    {
        $client = new FakeApiClient([$this->listJson([['id' => 'UC123']])]);
        $decoder = new ResponseDecoder;
        $channels = new Channels($client, $decoder, new Search($client, $decoder));

        $channels->byHandle('@Google', ['part' => 'id', 'hl' => 'ko']);

        $this->assertSame('id', $client->requests[0]['params']['part']);
        $this->assertSame('ko', $client->requests[0]['params']['hl']);
    }

    public function test_channel_by_handle_returns_null_when_not_found(): void
    {
        $client = new FakeApiClient([$this->listJson([])]);
        $decoder = new ResponseDecoder;

        $this->assertNull((new Channels($client, $decoder, new Search($client, $decoder)))->byHandle('@missing'));
    }

    public function test_url_parser_resolves_channel_url_types(): void
    {
        $this->assertSame(['type' => 'id', 'value' => 'UC1'], UrlParser::channel('https://www.youtube.com/channel/UC1'));
        $this->assertSame(['type' => 'username', 'value' => 'google'], UrlParser::channel('https://www.youtube.com/user/google'));
        $this->assertSame(['type' => 'search', 'value' => 'name'], UrlParser::channel('https://www.youtube.com/c/name'));
        $this->assertSame(['type' => 'search', 'value' => 'name'], UrlParser::channel('https://www.youtube.com/name'));
    }

    public function test_url_parser_rejects_reserved_paths(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not look like a Youtube Channel URL');

        UrlParser::channel('https://www.youtube.com/feed/trending');
    }
}
