<?php

namespace Cable8mm\Youtube\Tests\Resources;

use Cable8mm\Youtube\Http\ApiClient;

/**
 * ApiClient that records requests and returns canned responses instead of calling YouTube.
 */
class FakeApiClient extends ApiClient
{
    public array $requests = [];

    public function __construct(public array $responses = [])
    {
        parent::__construct('test_key');
    }

    protected function send(string $url, array $params): string
    {
        $this->requests[] = ['url' => $url, 'params' => $params];

        return array_shift($this->responses) ?? '{"kind":"k","etag":"e","items":[]}';
    }
}
