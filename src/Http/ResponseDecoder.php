<?php

namespace Cable8mm\Youtube\Http;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;

class ResponseDecoder
{
    /**
     * Decode the response from youtube, extract the single resource object.
     *
     * @return \StdClass|null
     *
     * @throws YoutubeApiException
     */
    public function single(string $apiData)
    {
        $items = $this->items($this->decode($apiData));

        return $items[0] ?? null;
    }

    /**
     * Decode the response from youtube, extract the multiple resource objects.
     *
     * @return array|null
     *
     * @throws YoutubeApiException
     */
    public function multiple(string $apiData)
    {
        return $this->items($this->decode($apiData));
    }

    /**
     * Decode the response from youtube, extract the list of resource objects
     * together with its pagination info.
     *
     * @return array{results: array|false, info: array}
     *
     * @throws YoutubeApiException
     */
    public function page(string $apiData): array
    {
        $resObj = $this->decode($apiData);

        $info = [
            'kind' => $resObj->kind,
            'etag' => $resObj->etag,
            'prevPageToken' => $resObj->prevPageToken ?? null,
            'nextPageToken' => $resObj->nextPageToken ?? null,
        ];

        if (isset($resObj->pageInfo)) {
            $info['resultsPerPage'] = $resObj->pageInfo->resultsPerPage;
            $info['totalResults'] = $resObj->pageInfo->totalResults;
        }

        return [
            'results' => $this->items($resObj) ?? false,
            'info' => $info,
        ];
    }

    /**
     * Decode the response from youtube, extract the list of resource objects.
     *
     * @return array|false
     *
     * @throws YoutubeApiException
     */
    public function list(string $apiData)
    {
        return $this->page($apiData)['results'];
    }

    /**
     * @throws YoutubeApiException
     */
    protected function decode(string $apiData): object
    {
        $resObj = json_decode($apiData);
        $this->handleApiError($resObj);

        return $resObj;
    }

    protected function items(object $resObj): ?array
    {
        if (isset($resObj->items) && is_array($resObj->items) && count($resObj->items) > 0) {
            return $resObj->items;
        }

        return null;
    }

    /**
     * Handle API error response from YouTube
     *
     * @throws YoutubeApiException
     */
    protected function handleApiError(object $resObj): void
    {
        if (isset($resObj->error)) {
            throw YoutubeApiException::fromApiError($resObj->error);
        }
    }
}
