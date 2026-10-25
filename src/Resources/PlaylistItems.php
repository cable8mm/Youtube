<?php

namespace Cable8mm\Youtube\Resources;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;

class PlaylistItems extends Resource
{
    /**
     * @throws YoutubeApiException
     */
    public function byPlaylistId(string $playlistId, string $pageToken = '', int $maxResults = 50, array $part = ['id', 'snippet', 'contentDetails', 'status']): array
    {
        return $this->fetchPaginated('playlistItems.list', [
            'playlistId' => $playlistId,
            'part' => implode(',', $part),
            'maxResults' => $maxResults,
            'pageToken' => $pageToken,
        ]);
    }
}
