<?php

namespace Cable8mm\Youtube\Resources;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;
use Cable8mm\Youtube\Http\ApiClient;
use Cable8mm\Youtube\Http\ResponseDecoder;
use Cable8mm\Youtube\Support\UrlParser;

class Channels extends Resource
{
    public const DEFAULT_PART = ['id', 'snippet', 'contentDetails', 'statistics'];

    public function __construct(
        ApiClient $client,
        ResponseDecoder $decoder,
        protected Search $search,
    ) {
        parent::__construct($client, $decoder);
    }

    /**
     * @return \StdClass|null
     *
     * @throws YoutubeApiException
     */
    public function byName(string $username, array $optionalParams = [], array $part = self::DEFAULT_PART)
    {
        $apiData = $this->client->call('channels.list', array_merge([
            'forUsername' => $username,
            'part' => implode(',', $part),
        ], $optionalParams));

        return $this->decoder->single($apiData);
    }

    /**
     * @return \StdClass|array|null
     *
     * @throws YoutubeApiException
     */
    public function byId(array|string $id, array $optionalParams = [], array $part = self::DEFAULT_PART)
    {
        return $this->fetchById('channels.list', $id, array_merge([
            'part' => implode(',', $part),
        ], $optionalParams));
    }

    /**
     * Search for a channel by name and fetch the first match.
     *
     * @return \StdClass|null
     *
     * @throws YoutubeApiException
     */
    public function searchByName(string $username, int $maxResults = 1, array $part = ['id', 'snippet'])
    {
        $search = $this->search->advanced([
            'q' => $username,
            'part' => implode(',', $part),
            'type' => 'channel',
            'maxResults' => $maxResults,
        ]);

        if (! empty($search[0]->snippet->channelId)) {
            return $this->byId($search[0]->snippet->channelId);
        }

        return null;
    }

    /**
     * Get the channel object by supplying the URL of the channel page
     *
     * @return \StdClass|null
     *
     * @throws YoutubeApiException
     * @throws \InvalidArgumentException
     */
    public function fromUrl(string $youtube_url)
    {
        ['type' => $type, 'value' => $value] = UrlParser::channel($youtube_url);

        return match ($type) {
            'id' => $this->byId($value),
            'username' => $this->byName($value),
            'search' => $this->searchByName($value),
        };
    }
}
