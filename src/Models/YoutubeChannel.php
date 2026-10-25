<?php

namespace Cable8mm\Youtube\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A YouTube channel stored in the host application (Channel Record).
 */
class YoutubeChannel extends Model
{
    protected $fillable = [
        'channel_id',
        'handle',
        'title',
        'uploads_playlist_id',
        'is_active',
        'sync_status',
        'last_synced_at',
        'last_error_reason',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<YoutubeVideo, $this>
     */
    public function videos(): HasMany
    {
        return $this->hasMany(YoutubeVideo::class);
    }

    /**
     * @return HasMany<YoutubeSyncError, $this>
     */
    public function syncErrors(): HasMany
    {
        return $this->hasMany(YoutubeSyncError::class);
    }

    /**
     * The host model this channel belongs to, if any.
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
