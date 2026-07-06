<?php

namespace Cable8mm\Youtube\Exceptions;

use Exception;

class YoutubeApiException extends Exception
{
    /**
     * Create a new YouTube API exception instance.
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception from YouTube API error response.
     */
    public static function fromApiError(object $errorObj): self
    {
        $message = 'Error '.$errorObj->code.' '.$errorObj->message;
        if (isset($errorObj->errors[0])) {
            $message .= ' : '.$errorObj->errors[0]->reason;
        }

        return new self($message, (int) $errorObj->code);
    }
}
