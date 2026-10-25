<?php

namespace Cable8mm\Youtube\Resources;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;

class CommentThreads extends Resource
{
    public const DEFAULT_PART = ['id', 'replies', 'snippet'];

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function list(?string $channelId = null, ?string $id = null, ?string $videoId = null, int $maxResults = 20, ?string $order = null, array $part = self::DEFAULT_PART, bool $pageInfo = false)
    {
        return $this->fetchList('commentThreads.list', array_filter([
            'channelId' => $channelId,
            'id' => $id,
            'videoId' => $videoId,
            'maxResults' => $maxResults,
            'part' => implode(',', $part),
            'order' => $order,
        ]), $pageInfo);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function byVideoId(?string $videoId = null, int $maxResults = 20, ?string $order = null, array $part = self::DEFAULT_PART, bool $pageInfo = false)
    {
        return $this->list(null, null, $videoId, $maxResults, $order, $part, $pageInfo);
    }
}
