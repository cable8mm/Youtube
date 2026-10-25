<?php

namespace Cable8mm\Youtube\Tests\Models\Fixtures;

use Cable8mm\Youtube\Concerns\HasYoutubeChannels;
use Illuminate\Database\Eloquent\Model;

/**
 * A host-application model that owns channels, for tests.
 */
class Artist extends Model
{
    use HasYoutubeChannels;

    protected $fillable = ['name'];
}
