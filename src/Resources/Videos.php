<?php

namespace Cable8mm\Youtube\Resources;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;

class Videos extends Resource
{
    public const DEFAULT_PART = ['id', 'snippet', 'contentDetails', 'player', 'statistics', 'status'];

    /**
     * @return \StdClass|array|null
     *
     * @throws YoutubeApiException
     */
    public function info(array|string $vId, array $part = self::DEFAULT_PART)
    {
        return $this->fetchById('videos.list', $vId, [
            'part' => implode(',', $part),
        ]);
    }

    /**
     * @return \StdClass|array|null
     *
     * @throws YoutubeApiException
     */
    public function localizedInfo(array|string $vId, string $language, array $part = self::DEFAULT_PART)
    {
        return $this->fetchById('videos.list', $vId, [
            'hl' => $language,
            'part' => implode(',', $part),
        ]);
    }

    /**
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function popular(string $regionCode, int $maxResults = 10, array $part = self::DEFAULT_PART)
    {
        return $this->fetchList('videos.list', [
            'chart' => 'mostPopular',
            'part' => implode(',', $part),
            'regionCode' => $regionCode,
            'maxResults' => $maxResults,
        ]);
    }
}
