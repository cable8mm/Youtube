<?php

namespace Cable8mm\Youtube\Models;

use Cable8mm\Youtube\Support\Duration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A video of a stored channel (Video Record).
 */
class YoutubeVideo extends Model
{
    protected $fillable = [
        'youtube_channel_id',
        'video_id',
        'title',
        'description',
        'published_at',
        'duration',
        'thumbnail_url',
        'view_count',
        'like_count',
        'comment_count',
        'unavailable_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'unavailable_at' => 'datetime',
            'view_count' => 'integer',
            'like_count' => 'integer',
            'comment_count' => 'integer',
        ];
    }

    /**
     * The stored ISO 8601 duration as `M:SS`, or `H:MM:SS` when it lasts an hour or more.
     */
    public function durationLabel(): ?string
    {
        return Duration::format($this->duration);
    }

    /**
     * @return BelongsTo<YoutubeChannel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(YoutubeChannel::class, 'youtube_channel_id');
    }
}
