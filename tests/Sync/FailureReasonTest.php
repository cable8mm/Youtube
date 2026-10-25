<?php

namespace Cable8mm\Youtube\Tests\Sync;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;
use Cable8mm\Youtube\Sync\FailureReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FailureReasonTest extends TestCase
{
    private static function apiError(int $code, string $message, string $reason): YoutubeApiException
    {
        return YoutubeApiException::fromApiError((object) [
            'code' => $code,
            'message' => $message,
            'errors' => [(object) ['reason' => $reason]],
        ]);
    }

    /**
     * Error bodies follow responses observed live (PRODUCT_SPEC.md §12 row 7, §19.1)
     * or the YouTube Data API error reference.
     */
    public static function apiErrors(): array
    {
        return [
            'observed 429 rateLimitExceeded' => [429, "Quota exceeded for quota metric 'Search Queries' and limit 'Search Queries per day' of service 'youtube.googleapis.com' for consumer 'project_number:123'.", 'rateLimitExceeded', FailureReason::QUOTA_EXCEEDED],
            'documented 403 quotaExceeded' => [403, 'The request cannot be completed because you have exceeded your quota.', 'quotaExceeded', FailureReason::QUOTA_EXCEEDED],
            'documented 403 dailyLimitExceeded' => [403, 'Daily Limit Exceeded', 'dailyLimitExceeded', FailureReason::QUOTA_EXCEEDED],
            'observed 400 invalid key' => [400, 'API key not valid. Please pass a valid API key.', 'badRequest', FailureReason::INVALID_KEY],
            'documented 403 forbidden' => [403, 'Access forbidden. The request may not be properly authorized.', 'forbidden', FailureReason::FORBIDDEN],
            'documented 404 playlistNotFound' => [404, "The playlist identified with the request's playlistId parameter cannot be found.", 'playlistNotFound', FailureReason::CHANNEL_NOT_FOUND],
            'documented 404 channelNotFound' => [404, "The channel identified with the request's channelId parameter cannot be found.", 'channelNotFound', FailureReason::CHANNEL_NOT_FOUND],
            'documented 500 backendError' => [500, 'Backend Error', 'backendError', FailureReason::UNKNOWN],
            'other 400 badRequest' => [400, 'Invalid value for parameter part.', 'badRequest', FailureReason::UNKNOWN],
        ];
    }

    #[DataProvider('apiErrors')]
    public function test_api_errors_map_to_failure_reasons(int $code, string $message, string $reason, string $expected): void
    {
        $this->assertSame($expected, FailureReason::fromThrowable(self::apiError($code, $message, $reason)));
    }

    public function test_transport_errors_map_to_transport(): void
    {
        $this->assertSame(FailureReason::TRANSPORT, FailureReason::fromThrowable(new YoutubeApiException('Curl Error : timeout')));
        $this->assertSame(FailureReason::TRANSPORT, FailureReason::fromThrowable(new YoutubeApiException('Invalid response from Youtube API : Syntax error')));
    }

    public function test_other_throwables_map_to_unknown(): void
    {
        $this->assertSame(FailureReason::UNKNOWN, FailureReason::fromThrowable(new RuntimeException('boom')));
        $this->assertSame(FailureReason::UNKNOWN, FailureReason::fromThrowable(new \TypeError('bad')));
        $this->assertSame(FailureReason::UNKNOWN, FailureReason::fromThrowable(new YoutubeApiException('Channel not found : UC1')));
    }

    public function test_reason_is_read_only_from_api_error_messages(): void
    {
        // A non-API message that happens to end like a reason is not classified by it.
        $this->assertSame(FailureReason::UNKNOWN, FailureReason::fromThrowable(new YoutubeApiException('Something : quotaExceeded')));
    }
}
