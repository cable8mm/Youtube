<?php

namespace Cable8mm\Youtube\Resources;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;
use Cable8mm\Youtube\Http\ApiClient;
use Cable8mm\Youtube\Http\ResponseDecoder;

abstract class Resource
{
    public function __construct(
        protected ApiClient $client,
        protected ResponseDecoder $decoder,
    ) {}

    /**
     * Fetch one resource for a string id, or many for an array of ids.
     *
     * @return \StdClass|array|null
     *
     * @throws YoutubeApiException
     */
    protected function fetchById(string $endpoint, array|string $id, array $params)
    {
        $params = ['id' => is_array($id) ? implode(',', $id) : $id] + $params;

        $apiData = $this->client->call($endpoint, $params);

        if (is_array($id)) {
            return $this->decoder->multiple($apiData);
        }

        return $this->decoder->single($apiData);
    }

    /**
     * Fetch a list, optionally wrapped with its pagination info.
     *
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    protected function fetchList(string $endpoint, array $params, bool $pageInfo = false)
    {
        $page = $this->decoder->page($this->client->call($endpoint, $params));

        return $pageInfo ? $page : $page['results'];
    }

    /**
     * Fetch a list wrapped with the totalResults/nextPageToken/prevPageToken info.
     *
     * @throws YoutubeApiException
     */
    protected function fetchPaginated(string $endpoint, array $params): array
    {
        $page = $this->decoder->page($this->client->call($endpoint, $params));

        return [
            'results' => $page['results'],
            'info' => [
                'totalResults' => $page['info']['totalResults'] ?? 0,
                'nextPageToken' => $page['info']['nextPageToken'] ?? false,
                'prevPageToken' => $page['info']['prevPageToken'] ?? false,
            ],
        ];
    }
}
