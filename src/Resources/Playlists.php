<?php

namespace Cable8mm\Youtube\Resources;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;

class Playlists extends Resource
{
    public const DEFAULT_PART = ['id', 'snippet', 'status'];

    /**
     * @throws YoutubeApiException
     */
    public function byChannelId(string $channelId, array $optionalParams = [], array $part = self::DEFAULT_PART): array
    {
        return $this->fetchPaginated('playlists.list', array_merge([
            'channelId' => $channelId,
            'part' => implode(',', $part),
        ], $optionalParams));
    }

    /**
     * @return \StdClass|array|null
     *
     * @throws YoutubeApiException
     */
    public function byId(array|string $id, array $part = self::DEFAULT_PART)
    {
        return $this->fetchById('playlists.list', $id, [
            'part' => implode(',', $part),
        ]);
    }
}
