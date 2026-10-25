<?php

namespace Cable8mm\Youtube\Tests\Http;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;
use Cable8mm\Youtube\Http\ResponseDecoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResponseDecoderTest extends TestCase
{
    public static function invalidResponses(): array
    {
        return [
            'empty body' => [''],
            'html error page' => ['<html>502 Bad Gateway</html>'],
            'truncated json' => ['{"items":['],
            'json scalar' => ['"ok"'],
        ];
    }

    #[DataProvider('invalidResponses')]
    public function test_single_throws_api_exception_for_invalid_response(string $body): void
    {
        $this->expectException(YoutubeApiException::class);
        $this->expectExceptionMessage('Invalid response from Youtube API');

        (new ResponseDecoder)->single($body);
    }

    #[DataProvider('invalidResponses')]
    public function test_page_throws_api_exception_for_invalid_response(string $body): void
    {
        $this->expectException(YoutubeApiException::class);

        (new ResponseDecoder)->page($body);
    }

    public function test_page_tolerates_missing_kind_and_etag(): void
    {
        $page = (new ResponseDecoder)->page('{"items":[]}');

        $this->assertFalse($page['results']);
        $this->assertNull($page['info']['kind']);
        $this->assertNull($page['info']['etag']);
    }
}
