<?php

namespace Cable8mm\Youtube\Models;

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
     * @return BelongsTo<YoutubeChannel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(YoutubeChannel::class, 'youtube_channel_id');
    }
}
