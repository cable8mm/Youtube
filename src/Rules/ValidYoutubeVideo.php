<?php

namespace Cable8mm\Youtube\Rules;

use Cable8mm\Youtube\Youtube;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidYoutubeVideo implements ValidationRule
{
    /**
     * @param  Youtube|null  $youtube  Client to look the video up with; resolved from the container when omitted.
     */
    public function __construct(protected ?Youtube $youtube = null) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $videoId = Youtube::parseVidFromURL($value);

            // 만약 videoId를 파싱하지 못했다면 유효하지 않음
            if (! $videoId) {
                $fail('The :attribute does not look like a valid Youtube URL.');

                return;
            }

            $youtube = $this->youtube ?? app(Youtube::class);
            $video = $youtube->getVideoInfo($videoId, ['id']);

            if ($video === null) {
                $fail('The :attribute does not exist on Youtube.');
            }
        } catch (\Exception $exception) {
            $fail('The :attribute could not be validated.');
        }
    }
}
