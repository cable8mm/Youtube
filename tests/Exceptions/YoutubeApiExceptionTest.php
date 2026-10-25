<?php

namespace Cable8mm\Youtube\Tests\Exceptions;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;
use PHPUnit\Framework\TestCase;

class YoutubeApiExceptionTest extends TestCase
{
    public function test_constructor_accepts_previous_throwable(): void
    {
        $previous = new \RuntimeException('cause');

        $exception = new YoutubeApiException('wrapped', 500, $previous);

        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame(500, $exception->getCode());
    }

    public function test_from_api_error_builds_message_and_code(): void
    {
        $exception = YoutubeApiException::fromApiError((object) [
            'code' => 403,
            'message' => 'Forbidden',
            'errors' => [(object) ['reason' => 'quotaExceeded']],
        ]);

        $this->assertSame('Error 403 Forbidden : quotaExceeded', $exception->getMessage());
        $this->assertSame(403, $exception->getCode());
    }
}
