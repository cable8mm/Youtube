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
     * Run the validation rule. It never throws.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute does not look like a valid Youtube URL.');

            return;
        }

        try {
            $videoId = Youtube::parseVidFromURL($value);
        } catch (\InvalidArgumentException) {
            $fail('The :attribute does not look like a valid Youtube URL.');

            return;
        }

        try {
            $youtube = $this->youtube ?? app(Youtube::class);
            $video = $youtube->getVideoInfo($videoId, ['id']);
        } catch (\Exception) {
            $fail('The :attribute could not be validated.');

            return;
        }

        if ($video === null) {
            $fail('The :attribute does not exist on Youtube.');
        }
    }
}
