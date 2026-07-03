<?php

namespace Cable8mm\Youtube;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;
use Carbon\Carbon;

class Youtube
{
    /**
     * @var array
     */
    protected $APIs = [
        'categories.list' => 'https://www.googleapis.com/youtube/v3/videoCategories',
        'videos.list' => 'https://www.googleapis.com/youtube/v3/videos',
        'search.list' => 'https://www.googleapis.com/youtube/v3/search',
        'channels.list' => 'https://www.googleapis.com/youtube/v3/channels',
        'playlists.list' => 'https://www.googleapis.com/youtube/v3/playlists',
        'playlistItems.list' => 'https://www.googleapis.com/youtube/v3/playlistItems',
        'activities' => 'https://www.googleapis.com/youtube/v3/activities',
        'commentThreads.list' => 'https://www.googleapis.com/youtube/v3/commentThreads',
    ];

    /**
     * @var array
     */
    protected $youtube_reserved_urls = [
        '\/about\b',
        '\/account\b',
        '\/account_(.*)',
        '\/ads\b',
        '\/creators\b',
        '\/feed\b',
        '\/feed\/(.*)',
        '\/gaming\b',
        '\/gaming\/(.*)',
        '\/howyoutubeworks\b',
        '\/howyoutubeworks\/(.*)',
        '\/new\b',
        '\/playlist\b',
        '\/playlist\/(.*)',
        '\/reporthistory',
        '\/results\b',
        '\/shorts\b',
        '\/shorts\/(.*)',
        '\/t\/(.*)',
        '\/upload\b',
        '\/yt\/(.*)',
    ];

    /**
     * @var array
     */
    protected $page_info = [];

    /**
     * @var array
     */
    protected $config = [];

    protected bool $cacheEnabled = false;

    protected int $cacheTtl = 3600;

    /**
     * Constructor
     *
     * @param  string  $key
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(protected string $youtube_key, array $config = [])
    {
        if (empty($this->youtube_key)) {
            throw new \InvalidArgumentException('Google API key is Required, please visit https://console.developers.google.com/');
        }
        $this->config['use-http-host'] = $config['use-http-host'] ?? false;
        $this->cacheEnabled = $config['cache_enabled'] ?? false;
        $this->cacheTtl = $config['cache_ttl'] ?? 3600;
    }

    public function useHttpHost(bool $setting): self
    {
        $this->config['use-http-host'] = $setting;

        return $this;
    }

    public function setApiKey(string $key): self
    {
        $this->youtube_key = $key;

        return $this;
    }

    public function getApiKey(): string
    {
        return $this->youtube_key;
    }

    /**
     * Enable or disable caching.
     */
    public function cache(bool $enabled = true): self
    {
        $this->cacheEnabled = $enabled;

        return $this;
    }

    /**
     * Set cache TTL.
     */
    public function setCacheTtl(int $ttl): self
    {
        $this->cacheTtl = $ttl;

        return $this;
    }

    /**
     * @return \StdClass|null
     *
     * @throws YoutubeApiException
     */
    public function getCategories(string $regionCode = 'US', array $part = ['snippet'])
    {
        $API_URL = $this->getApi('categories.list');
        $params = [
            'key' => $this->youtube_key,
            'part' => implode(',', $part),
            'regionCode' => $regionCode,
        ];

        $apiData = $this->api_get($API_URL, $params);

        return $this->decodeMultiple($apiData);
    }

    /**
     * @return array
     *
     * @throws YoutubeApiException
     */
    public function getCommentThreadsByVideoId(?string $videoId = null, int $maxResults = 20, ?string $order = null, array $part = ['id', 'replies', 'snippet'], bool $pageInfo = false)
    {
        return $this->getCommentThreads(null, null, $videoId, $maxResults, $order, $part, $pageInfo);
    }

    /**
     * @return array
     *
     * @throws YoutubeApiException
     */
    public function getCommentThreads(?string $channelId = null, ?string $id = null, ?string $videoId = null, int $maxResults = 20, ?string $order = null, array $part = ['id', 'replies', 'snippet'], bool $pageInfo = false)
    {
        $API_URL = $this->getApi('commentThreads.list');

        $params = array_filter([
            'channelId' => $channelId,
            'id' => $id,
            'videoId' => $videoId,
            'maxResults' => $maxResults,
            'part' => implode(',', $part),
            'order' => $order,
        ]);

        $apiData = $this->api_get($API_URL, $params);

        if ($pageInfo) {
            return [
                'results' => $this->decodeList($apiData),
                'info' => $this->page_info,
            ];
        }

        return $this->decodeList($apiData);
    }

    /**
     * @return \StdClass|array|null
     *
     * @throws YoutubeApiException
     */
    public function getVideoInfo(array|string $vId, array $part = ['id', 'snippet', 'contentDetails', 'player', 'statistics', 'status'])
    {
        $API_URL = $this->getApi('videos.list');
        $params = [
            'id' => is_array($vId) ? implode(',', $vId) : $vId,
            'key' => $this->youtube_key,
            'part' => implode(',', $part),
        ];

        $apiData = $this->api_get($API_URL, $params);

        if (is_array($vId)) {
            return $this->decodeMultiple($apiData);
        }

        return $this->decodeSingle($apiData);
    }

    /**
     * @param  array|string  $vId
     * @return \StdClass|array|null
     *
     * @throws YoutubeApiException
     */
    public function getLocalizedVideoInfo(mixed $vId, string $language, array $part = ['id', 'snippet', 'contentDetails', 'player', 'statistics', 'status'])
    {
        $API_URL = $this->getApi('videos.list');
        $params = [
            'id' => is_array($vId) ? implode(',', $vId) : $vId,
            'key' => $this->youtube_key,
            'hl' => $language,
            'part' => implode(',', $part),
        ];

        $apiData = $this->api_get($API_URL, $params);

        if (is_array($vId)) {
            return $this->decodeMultiple($apiData);
        }

        return $this->decodeSingle($apiData);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function getPopularVideos(string $regionCode, int $maxResults = 10, array $part = ['id', 'snippet', 'contentDetails', 'player', 'statistics', 'status'])
    {
        $API_URL = $this->getApi('videos.list');
        $params = [
            'chart' => 'mostPopular',
            'part' => implode(',', $part),
            'regionCode' => $regionCode,
            'maxResults' => $maxResults,
        ];

        $apiData = $this->api_get($API_URL, $params);

        return $this->decodeList($apiData);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function search(string $q, int $maxResults = 10, array $part = ['id', 'snippet'])
    {
        $params = [
            'q' => $q,
            'part' => implode(',', $part),
            'maxResults' => $maxResults,
        ];

        return $this->searchAdvanced($params);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function searchVideos(string $q, int $maxResults = 10, ?string $order = null, array $part = ['id'])
    {
        $params = [
            'q' => $q,
            'type' => 'video',
            'part' => implode(',', $part),
            'maxResults' => $maxResults,
        ];

        if (! empty($order)) {
            $params['order'] = $order;
        }

        return $this->searchAdvanced($params);
    }

    /**
     * @return array
     *
     * @throws YoutubeApiException
     */
    public function searchChannelVideos(string $q, string $channelId, int $maxResults = 10, ?string $order = null, array $part = ['id', 'snippet'], bool $pageInfo = false)
    {
        $params = [
            'q' => $q,
            'type' => 'video',
            'channelId' => $channelId,
            'part' => implode(',', $part),
            'maxResults' => $maxResults,
        ];

        if (! empty($order)) {
            $params['order'] = $order;
        }

        return $this->searchAdvanced($params, $pageInfo);
    }

    /**
     * @throws YoutubeApiException
     */
    public function getChannelVideos(string $channelId, int $maxResults, ?string $publishedBefore, bool $isFuture = false, string $pageToken = ''): array
    {
        $params = [
            'type' => 'video',
            'channelId' => $channelId,
            'part' => 'id,snippet',
            'maxResults' => $maxResults,
            'order' => 'date',
        ];

        if ($publishedBefore !== null) {
            if ($isFuture) {
                $params['publishedAfter'] = Carbon::create($publishedBefore)->addSecond()->toRfc3339String();
            } else {
                $params['publishedBefore'] = Carbon::create($publishedBefore)->subSecond()->toRfc3339String();
            }
        }

        if (! empty($pageToken)) {
            $params['pageToken'] = $pageToken;
        }

        return $this->searchAdvanced($params, true);
    }

    /**
     * @return array
     *
     * @throws YoutubeApiException
     */
    public function listChannelVideos(string $channelId, int $maxResults = 10, ?string $order = null, array $part = ['id', 'snippet'], bool $pageInfo = false)
    {
        $params = [
            'type' => 'video',
            'channelId' => $channelId,
            'part' => implode(',', $part),
            'maxResults' => $maxResults,
        ];

        if (! empty($order)) {
            $params['order'] = $order;
        }

        return $this->searchAdvanced($params, $pageInfo);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function searchAdvanced(array $params, bool $pageInfo = false)
    {
        if (empty($params) || (! isset($params['q']) && ! isset($params['channelId']) && ! isset($params['videoCategoryId']))) {
            throw new \InvalidArgumentException('at least the Search query or Channel ID or videoCategoryId must be supplied');
        }

        $API_URL = $this->getApi('search.list');
        $apiData = $this->api_get($API_URL, $params);

        if ($pageInfo) {
            return [
                'results' => $this->decodeList($apiData),
                'info' => $this->page_info,
            ];
        }

        return $this->decodeList($apiData);
    }

    /**
     * @throws YoutubeApiException
     */
    public function paginateResults(array $params, ?string $token = null): array
    {
        if (! is_null($token)) {
            $params['pageToken'] = $token;
        }

        return $this->searchAdvanced($params, true);
    }

    /**
     * @return \StdClass|null
     *
     * @throws YoutubeApiException
     */
    public function getChannelByName(string $username, array $optionalParams = [], array $part = ['id', 'snippet', 'contentDetails', 'statistics'])
    {
        $API_URL = $this->getApi('channels.list');
        $params = [
            'forUsername' => $username,
            'part' => implode(',', $part),
        ];

        $params = array_merge($params, $optionalParams);

        $apiData = $this->api_get($API_URL, $params);

        return $this->decodeSingle($apiData);
    }

    /**
     * @return \StdClass|null
     *
     * @throws YoutubeApiException
     */
    public function searchChannelByName(string $username, int $maxResults = 1, array $part = ['id', 'snippet'])
    {
        $params = [
            'q' => $username,
            'part' => implode(',', $part),
            'type' => 'channel',
            'maxResults' => $maxResults,
        ];

        $search = $this->searchAdvanced($params);

        if (! empty($search[0]->snippet->channelId)) {
            $channelId = $search[0]->snippet->channelId;

            return $this->getChannelById($channelId);
        }

        return null;
    }

    /**
     * @param  array|string  $id
     * @return \StdClass|array|null
     *
     * @throws YoutubeApiException
     */
    public function getChannelById(mixed $id, array $optionalParams = [], array $part = ['id', 'snippet', 'contentDetails', 'statistics'])
    {
        $API_URL = $this->getApi('channels.list');
        $params = [
            'id' => is_array($id) ? implode(',', $id) : $id,
            'part' => implode(',', $part),
        ];

        $params = array_merge($params, $optionalParams);

        $apiData = $this->api_get($API_URL, $params);

        if (is_array($id)) {
            return $this->decodeMultiple($apiData);
        }

        return $this->decodeSingle($apiData);
    }

    /**
     * @return array
     *
     * @throws YoutubeApiException
     */
    public function getPlaylistsByChannelId(string $channelId, array $optionalParams = [], array $part = ['id', 'snippet', 'status'])
    {
        $API_URL = $this->getApi('playlists.list');
        $params = [
            'channelId' => $channelId,
            'part' => implode(',', $part),
        ];

        $params = array_merge($params, $optionalParams);

        $apiData = $this->api_get($API_URL, $params);

        $result = ['results' => $this->decodeList($apiData)];
        $result['info']['totalResults'] = $this->page_info['totalResults'] ?? 0;
        $result['info']['nextPageToken'] = $this->page_info['nextPageToken'] ?? false;
        $result['info']['prevPageToken'] = $this->page_info['prevPageToken'] ?? false;

        return $result;
    }

    /**
     * @param  array|string  $id
     * @return \StdClass|array|null
     *
     * @throws YoutubeApiException
     */
    public function getPlaylistById(mixed $id, array $part = ['id', 'snippet', 'status'])
    {
        $API_URL = $this->getApi('playlists.list');
        $params = [
            'id' => is_array($id) ? implode(',', $id) : $id,
            'part' => implode(',', $part),
        ];

        $apiData = $this->api_get($API_URL, $params);

        if (is_array($id)) {
            return $this->decodeMultiple($apiData);
        }

        return $this->decodeSingle($apiData);
    }

    /**
     * @return array
     *
     * @throws YoutubeApiException
     */
    public function getPlaylistItemsByPlaylistId(string $playlistId, string $pageToken = '', int $maxResults = 50, array $part = ['id', 'snippet', 'contentDetails', 'status'])
    {
        $API_URL = $this->getApi('playlistItems.list');
        $params = [
            'playlistId' => $playlistId,
            'part' => implode(',', $part),
            'maxResults' => $maxResults,
            'pageToken' => $pageToken,
        ];

        $apiData = $this->api_get($API_URL, $params);

        $result = ['results' => $this->decodeList($apiData)];
        $result['info']['totalResults'] = $this->page_info['totalResults'] ?? 0;
        $result['info']['nextPageToken'] = $this->page_info['nextPageToken'] ?? false;
        $result['info']['prevPageToken'] = $this->page_info['prevPageToken'] ?? false;

        return $result;
    }

    /**
     * @return array
     *
     * @throws YoutubeApiException
     */
    public function getActivitiesByChannelId(string $channelId, array $part = ['id', 'snippet', 'contentDetails'], int $maxResults = 5, bool $pageInfo = false, string $pageToken = '')
    {
        if (empty($channelId)) {
            throw new \InvalidArgumentException('ChannelId must be supplied');
        }

        $API_URL = $this->getApi('activities');
        $params = [
            'channelId' => $channelId,
            'part' => implode(',', $part),
            'maxResults' => $maxResults,
            'pageToken' => $pageToken,
        ];

        $apiData = $this->api_get($API_URL, $params);

        if ($pageInfo) {
            return [
                'results' => $this->decodeList($apiData),
                'info' => $this->page_info,
            ];
        }

        return $this->decodeList($apiData);
    }

    /**
     * Parse a youtube URL to get the youtube Vid.
     * Support both full URL (www.youtube.com) and short URL (youtu.be)
     *
     *
     * @throws \InvalidArgumentException
     */
    public static function parseVidFromURL(string $youtube_url): string
    {
        if (strpos($youtube_url, 'youtube.com') !== false) {
            if (strpos($youtube_url, 'embed') !== false) {
                $path = static::_parse_url_path($youtube_url);
                $vid = substr($path, 7);

                return $vid;
            }

            $params = static::_parse_url_query($youtube_url);

            return $params['v'];
        }

        if (strpos($youtube_url, 'youtu.be') !== false) {
            $path = static::_parse_url_path($youtube_url);
            $vid = substr($path, 1);

            return $vid;
        }

        throw new \InvalidArgumentException('The supplied URL does not look like a Youtube URL');
    }

    /**
     * Get the channel object by supplying the URL of the channel page
     *
     * @return \StdClass|null
     *
     * @throws \InvalidArgumentException
     */
    public function getChannelFromURL(string $youtube_url)
    {
        if (strpos($youtube_url, 'youtube.com') === false) {
            throw new \InvalidArgumentException('The supplied URL does not look like a Youtube URL');
        }

        $path = static::_parse_url_path($youtube_url);
        $segments = explode('/', $path);

        if (strpos($path, '/channel/') === 0) {
            $channelId = $segments[count($segments) - 1];

            return $this->getChannelById($channelId);
        }

        if (strpos($path, '/user/') === 0) {
            $username = $segments[count($segments) - 1];

            return $this->getChannelByName($username);
        }

        if (strpos($path, '/c/') === 0) {
            $username = $segments[count($segments) - 1];

            return $this->searchChannelByName($username);
        }

        if (strpos($path, '/@') === 0) {
            $username = str_replace('@', '', $segments[count($segments) - 1]);

            return $this->searchChannelByName($username);
        }

        foreach ($this->youtube_reserved_urls as $r) {
            if (preg_match('/'.$r.'/', $path)) {
                throw new \InvalidArgumentException('The supplied URL does not look like a Youtube Channel URL');
            }
        }

        $username = $segments[1];

        return $this->searchChannelByName($username);
    }

    public function getApi(string $name): mixed
    {
        return $this->APIs[$name];
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
        $resObj = json_decode($apiData);
        $this->handleApiError($resObj);

        if (isset($resObj->items) && is_array($resObj->items) && count($resObj->items) > 0) {
            return $resObj->items[0];
        }

        return null;
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
        $resObj = json_decode($apiData);
        $this->handleApiError($resObj);

        if (isset($resObj->items) && is_array($resObj->items) && count($resObj->items) > 0) {
            return $resObj->items;
        }

        return null;
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
        $resObj = json_decode($apiData);
        $this->handleApiError($resObj);

        $this->page_info = [
            'kind' => $resObj->kind,
            'etag' => $resObj->etag,
            'prevPageToken' => null,
            'nextPageToken' => null,
        ];

        if (isset($resObj->pageInfo)) {
            $this->page_info['resultsPerPage'] = $resObj->pageInfo->resultsPerPage;
            $this->page_info['totalResults'] = $resObj->pageInfo->totalResults;
        }

        if (isset($resObj->prevPageToken)) {
            $this->page_info['prevPageToken'] = $resObj->prevPageToken;
        }

        if (isset($resObj->nextPageToken)) {
            $this->page_info['nextPageToken'] = $resObj->nextPageToken;
        }

        if (isset($resObj->items) && is_array($resObj->items) && count($resObj->items) > 0) {
            return $resObj->items;
        }

        return false;
    }

    /**
     * Handle API error response from YouTube
     *
     *
     * @throws YoutubeApiException
     */
    private function handleApiError(object $resObj): void
    {
        if (isset($resObj->error)) {
            throw YoutubeApiException::fromApiError($resObj->error);
        }
    }

    /**
     * Using CURL to issue a GET request.
     *
     * @throws YoutubeApiException
     */
    public function api_get(string $url, array $params): string
    {
        $params['key'] = $this->youtube_key;

        // Check cache first if enabled
        if ($this->cacheEnabled && function_exists('app') && app()->bound('cache')) {
            $cacheKey = $this->generateCacheKey($url, $params);
            $cached = app('cache')->get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        $tuCurl = curl_init();

        if (isset($_SERVER['HTTP_HOST']) && $this->config['use-http-host']) {
            curl_setopt($tuCurl, CURLOPT_HTTPHEADER, [
                'Referer: '.$_SERVER['HTTP_HOST'],
            ]);
        }

        curl_setopt($tuCurl, CURLOPT_URL, $url.(strpos($url, '?') === false ? '?' : '').http_build_query($params));
        curl_setopt($tuCurl, CURLOPT_RETURNTRANSFER, 1);
        $tuData = curl_exec($tuCurl);

        if (curl_errno($tuCurl)) {
            throw new YoutubeApiException('Curl Error : '.curl_error($tuCurl));
        }

        // Store in cache if enabled
        if ($this->cacheEnabled && function_exists('app') && app()->bound('cache')) {
            app('cache')->put($cacheKey, $tuData, $this->cacheTtl);
        }

        return $tuData;
    }

    /**
     * Generate cache key from URL and params.
     */
    private function generateCacheKey(string $url, array $params): string
    {
        ksort($params);

        return 'youtube_api_'.md5($url.http_build_query($params));
    }

    /**
     * Parse the input url string and return just the path part
     */
    public static function _parse_url_path(string $url): string
    {
        $array = parse_url($url);

        return $array['path'];
    }

    /**
     * Parse the input url string and return an array of query params
     */
    public static function _parse_url_query(string $url): array
    {
        $array = parse_url($url);
        $query = $array['query'];

        $queryParts = explode('&', $query);

        $params = [];
        foreach ($queryParts as $param) {
            $item = explode('=', $param);
            $params[$item[0]] = empty($item[1]) ? '' : $item[1];
        }

        return $params;
    }
}
