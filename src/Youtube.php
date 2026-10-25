<?php

namespace Cable8mm\Youtube;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;
use Cable8mm\Youtube\Http\ApiClient;
use Cable8mm\Youtube\Http\ResponseDecoder;
use Cable8mm\Youtube\Resources\Activities;
use Cable8mm\Youtube\Resources\Categories;
use Cable8mm\Youtube\Resources\Channels;
use Cable8mm\Youtube\Resources\CommentThreads;
use Cable8mm\Youtube\Resources\PlaylistItems;
use Cable8mm\Youtube\Resources\Playlists;
use Cable8mm\Youtube\Resources\Search;
use Cable8mm\Youtube\Resources\Videos;
use Cable8mm\Youtube\Support\UrlParser;

class Youtube
{
    /**
     * Pagination info of the last response decoded through decodeList().
     *
     * @var array
     */
    protected $page_info = [];

    protected ApiClient $client;

    protected ResponseDecoder $decoder;

    protected Categories $categories;

    protected Videos $videos;

    protected Search $searchResource;

    protected Channels $channels;

    protected Playlists $playlists;

    protected PlaylistItems $playlistItems;

    protected Activities $activities;

    protected CommentThreads $commentThreads;

    /**
     * Constructor
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(string $youtube_key, array $config = [])
    {
        if (empty($youtube_key)) {
            throw new \InvalidArgumentException('Google API key is Required, please visit https://console.developers.google.com/');
        }

        $this->client = new ApiClient(
            $youtube_key,
            $config['use-http-host'] ?? false,
            $config['cache_enabled'] ?? false,
            $config['cache_ttl'] ?? 3600,
        );
        $this->decoder = new ResponseDecoder;

        $this->categories = new Categories($this->client, $this->decoder);
        $this->videos = new Videos($this->client, $this->decoder);
        $this->searchResource = new Search($this->client, $this->decoder);
        $this->channels = new Channels($this->client, $this->decoder, $this->searchResource);
        $this->playlists = new Playlists($this->client, $this->decoder);
        $this->playlistItems = new PlaylistItems($this->client, $this->decoder);
        $this->activities = new Activities($this->client, $this->decoder);
        $this->commentThreads = new CommentThreads($this->client, $this->decoder);
    }

    /**
     * Get the underlying HTTP client.
     */
    public function getClient(): ApiClient
    {
        return $this->client;
    }

    public function useHttpHost(bool $setting): self
    {
        $this->client->useHttpHost($setting);

        return $this;
    }

    public function setApiKey(string $key): self
    {
        $this->client->setApiKey($key);

        return $this;
    }

    public function getApiKey(): string
    {
        return $this->client->getApiKey();
    }

    /**
     * Enable or disable caching.
     */
    public function cache(bool $enabled = true): self
    {
        $this->client->cache($enabled);

        return $this;
    }

    /**
     * Set cache TTL.
     */
    public function setCacheTtl(int $ttl): self
    {
        $this->client->setCacheTtl($ttl);

        return $this;
    }

    /**
     * @return array|null
     *
     * @throws YoutubeApiException
     */
    public function getCategories(string $regionCode = 'US', array $part = ['snippet'])
    {
        return $this->categories->list($regionCode, $part);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function getCommentThreadsByVideoId(?string $videoId = null, int $maxResults = 20, ?string $order = null, array $part = CommentThreads::DEFAULT_PART, bool $pageInfo = false)
    {
        return $this->commentThreads->byVideoId($videoId, $maxResults, $order, $part, $pageInfo);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function getCommentThreads(?string $channelId = null, ?string $id = null, ?string $videoId = null, int $maxResults = 20, ?string $order = null, array $part = CommentThreads::DEFAULT_PART, bool $pageInfo = false)
    {
        return $this->commentThreads->list($channelId, $id, $videoId, $maxResults, $order, $part, $pageInfo);
    }

    /**
     * @return \StdClass|array|null
     *
     * @throws YoutubeApiException
     */
    public function getVideoInfo(array|string $vId, array $part = Videos::DEFAULT_PART)
    {
        return $this->videos->info($vId, $part);
    }

    /**
     * @param  array|string  $vId
     * @return \StdClass|array|null
     *
     * @throws YoutubeApiException
     */
    public function getLocalizedVideoInfo(mixed $vId, string $language, array $part = Videos::DEFAULT_PART)
    {
        return $this->videos->localizedInfo($vId, $language, $part);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function getPopularVideos(string $regionCode, int $maxResults = 10, array $part = Videos::DEFAULT_PART)
    {
        return $this->videos->popular($regionCode, $maxResults, $part);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function search(string $q, int $maxResults = 10, array $part = ['id', 'snippet'])
    {
        return $this->searchResource->query($q, $maxResults, $part);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function searchVideos(string $q, int $maxResults = 10, ?string $order = null, array $part = ['id'])
    {
        return $this->searchResource->videos($q, $maxResults, $order, $part);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function searchChannelVideos(string $q, string $channelId, int $maxResults = 10, ?string $order = null, array $part = ['id', 'snippet'], bool $pageInfo = false)
    {
        return $this->searchResource->channelVideos($q, $channelId, $maxResults, $order, $part, $pageInfo);
    }

    /**
     * @throws YoutubeApiException
     */
    public function getChannelVideos(string $channelId, int $maxResults, ?string $publishedBefore, bool $isFuture = false, string $pageToken = ''): array
    {
        return $this->searchResource->channelVideosByDate($channelId, $maxResults, $publishedBefore, $isFuture, $pageToken);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function listChannelVideos(string $channelId, int $maxResults = 10, ?string $order = null, array $part = ['id', 'snippet'], bool $pageInfo = false)
    {
        return $this->searchResource->listChannelVideos($channelId, $maxResults, $order, $part, $pageInfo);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     * @throws \InvalidArgumentException
     */
    public function searchAdvanced(array $params, bool $pageInfo = false)
    {
        return $this->searchResource->advanced($params, $pageInfo);
    }

    /**
     * @throws YoutubeApiException
     */
    public function paginateResults(array $params, ?string $token = null): array
    {
        return $this->searchResource->paginate($params, $token);
    }

    /**
     * @return \StdClass|null
     *
     * @throws YoutubeApiException
     */
    public function getChannelByName(string $username, array $optionalParams = [], array $part = Channels::DEFAULT_PART)
    {
        return $this->channels->byName($username, $optionalParams, $part);
    }

    /**
     * @return \StdClass|null
     *
     * @throws YoutubeApiException
     */
    public function searchChannelByName(string $username, int $maxResults = 1, array $part = ['id', 'snippet'])
    {
        return $this->channels->searchByName($username, $maxResults, $part);
    }

    /**
     * @param  array|string  $id
     * @return \StdClass|array|null
     *
     * @throws YoutubeApiException
     */
    public function getChannelById(mixed $id, array $optionalParams = [], array $part = Channels::DEFAULT_PART)
    {
        return $this->channels->byId($id, $optionalParams, $part);
    }

    /**
     * @return array
     *
     * @throws YoutubeApiException
     */
    public function getPlaylistsByChannelId(string $channelId, array $optionalParams = [], array $part = Playlists::DEFAULT_PART)
    {
        return $this->playlists->byChannelId($channelId, $optionalParams, $part);
    }

    /**
     * @param  array|string  $id
     * @return \StdClass|array|null
     *
     * @throws YoutubeApiException
     */
    public function getPlaylistById(mixed $id, array $part = Playlists::DEFAULT_PART)
    {
        return $this->playlists->byId($id, $part);
    }

    /**
     * @return array
     *
     * @throws YoutubeApiException
     */
    public function getPlaylistItemsByPlaylistId(string $playlistId, string $pageToken = '', int $maxResults = 50, array $part = ['id', 'snippet', 'contentDetails', 'status'])
    {
        return $this->playlistItems->byPlaylistId($playlistId, $pageToken, $maxResults, $part);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     * @throws \InvalidArgumentException
     */
    public function getActivitiesByChannelId(string $channelId, array $part = ['id', 'snippet', 'contentDetails'], int $maxResults = 5, bool $pageInfo = false, string $pageToken = '')
    {
        return $this->activities->byChannelId($channelId, $part, $maxResults, $pageInfo, $pageToken);
    }

    /**
     * Parse a youtube URL to get the youtube Vid.
     * Support both full URL (www.youtube.com) and short URL (youtu.be)
     *
     * @throws \InvalidArgumentException
     */
    public static function parseVidFromURL(string $youtube_url): string
    {
        return UrlParser::videoId($youtube_url);
    }

    /**
     * Get the channel object by supplying the URL of the channel page
     *
     * @return \StdClass|null
     *
     * @throws YoutubeApiException
     * @throws \InvalidArgumentException
     */
    public function getChannelFromURL(string $youtube_url)
    {
        return $this->channels->fromUrl($youtube_url);
    }

    public function getApi(string $name): mixed
    {
        return ApiClient::ENDPOINTS[$name];
    }

    /**
     * Decode the response from youtube, extract the single resource object.
     *
     * @return \StdClass|null
     *
     * @throws YoutubeApiException
     */
    public function decodeSingle(string &$apiData)
    {
        return $this->decoder->single($apiData);
    }

    /**
     * Decode the response from youtube, extract the multiple resource objects.
     *
     * @return array|null
     *
     * @throws YoutubeApiException
     */
    public function decodeMultiple(string &$apiData)
    {
        return $this->decoder->multiple($apiData);
    }

    /**
     * Decode the response from youtube, extract the list of resource objects
     *
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function decodeList(string &$apiData)
    {
        $page = $this->decoder->page($apiData);
        $this->page_info = $page['info'];

        return $page['results'];
    }

    /**
     * Using CURL to issue a GET request.
     *
     * @throws YoutubeApiException
     */
    public function api_get(string $url, array $params): string
    {
        return $this->client->get($url, $params);
    }

    /**
     * Parse the input url string and return just the path part
     */
    public static function _parse_url_path(string $url): string
    {
        return UrlParser::path($url);
    }

    /**
     * Parse the input url string and return an array of query params
     */
    public static function _parse_url_query(string $url): array
    {
        return UrlParser::query($url);
    }
}
