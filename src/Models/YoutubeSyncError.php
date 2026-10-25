<?php

namespace Cable8mm\Youtube\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One failed Sync of a channel (Sync Error Record).
 */
class YoutubeSyncError extends Model
{
    protected $fillable = [
        'youtube_channel_id',
        'reason',
        'http_code',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'http_code' => 'integer',
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
