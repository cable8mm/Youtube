<?php

namespace Cable8mm\Youtube\Resources;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;
use Carbon\Carbon;

class Search extends Resource
{
    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function query(string $q, int $maxResults = 10, array $part = ['id', 'snippet'])
    {
        return $this->advanced([
            'q' => $q,
            'part' => implode(',', $part),
            'maxResults' => $maxResults,
        ]);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function videos(string $q, int $maxResults = 10, ?string $order = null, array $part = ['id'])
    {
        return $this->advanced($this->withOrder([
            'q' => $q,
            'type' => 'video',
            'part' => implode(',', $part),
            'maxResults' => $maxResults,
        ], $order));
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function channelVideos(string $q, string $channelId, int $maxResults = 10, ?string $order = null, array $part = ['id', 'snippet'], bool $pageInfo = false)
    {
        return $this->advanced($this->withOrder([
            'q' => $q,
            'type' => 'video',
            'channelId' => $channelId,
            'part' => implode(',', $part),
            'maxResults' => $maxResults,
        ], $order), $pageInfo);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function listChannelVideos(string $channelId, int $maxResults = 10, ?string $order = null, array $part = ['id', 'snippet'], bool $pageInfo = false)
    {
        return $this->advanced($this->withOrder([
            'type' => 'video',
            'channelId' => $channelId,
            'part' => implode(',', $part),
            'maxResults' => $maxResults,
        ], $order), $pageInfo);
    }

    /**
     * List a channel's videos by date, before (or after, when $isFuture) the given time.
     *
     * @throws YoutubeApiException
     */
    public function channelVideosByDate(string $channelId, int $maxResults, ?string $publishedBefore, bool $isFuture = false, string $pageToken = ''): array
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

        return $this->advanced($params, true);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     * @throws \InvalidArgumentException
     */
    public function advanced(array $params, bool $pageInfo = false)
    {
        if (empty($params) || (! isset($params['q']) && ! isset($params['channelId']) && ! isset($params['videoCategoryId']))) {
            throw new \InvalidArgumentException('at least the Search query or Channel ID or videoCategoryId must be supplied');
        }

        return $this->fetchList('search.list', $params, $pageInfo);
    }

    /**
     * @throws YoutubeApiException
     */
    public function paginate(array $params, ?string $token = null): array
    {
        if (! is_null($token)) {
            $params['pageToken'] = $token;
        }

        return $this->advanced($params, true);
    }

    protected function withOrder(array $params, ?string $order): array
    {
        if (! empty($order)) {
            $params['order'] = $order;
        }

        return $params;
    }
}
