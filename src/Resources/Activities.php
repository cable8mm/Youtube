<?php

namespace Cable8mm\Youtube\Resources;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;

class Activities extends Resource
{
    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     * @throws \InvalidArgumentException
     */
    public function byChannelId(string $channelId, array $part = ['id', 'snippet', 'contentDetails'], int $maxResults = 5, bool $pageInfo = false, string $pageToken = '')
    {
        if (empty($channelId)) {
            throw new \InvalidArgumentException('ChannelId must be supplied');
        }

        return $this->fetchList('activities', [
            'channelId' => $channelId,
            'part' => implode(',', $part),
            'maxResults' => $maxResults,
            'pageToken' => $pageToken,
        ], $pageInfo);
    }
}
