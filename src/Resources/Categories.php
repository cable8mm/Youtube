<?php

namespace Cable8mm\Youtube\Resources;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;

class Categories extends Resource
{
    /**
     * @return array|null
     *
     * @throws YoutubeApiException
     */
    public function list(string $regionCode = 'US', array $part = ['snippet'])
    {
        $apiData = $this->client->call('categories.list', [
            'part' => implode(',', $part),
            'regionCode' => $regionCode,
        ]);

        return $this->decoder->multiple($apiData);
    }
}
