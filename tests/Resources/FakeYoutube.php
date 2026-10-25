<?php

namespace Cable8mm\Youtube\Tests\Resources;

use Cable8mm\Youtube\Resources\Activities;
use Cable8mm\Youtube\Resources\Categories;
use Cable8mm\Youtube\Resources\Channels;
use Cable8mm\Youtube\Resources\CommentThreads;
use Cable8mm\Youtube\Resources\PlaylistItems;
use Cable8mm\Youtube\Resources\Playlists;
use Cable8mm\Youtube\Resources\Search;
use Cable8mm\Youtube\Resources\Videos;
use Cable8mm\Youtube\Youtube;

/**
 * A Client whose every operation group sends through a FakeApiClient.
 *
 * Bind it in place of the Shared Client for offline sync and import tests.
 */
class FakeYoutube extends Youtube
{
    public function __construct(public FakeApiClient $fake)
    {
        parent::__construct('test_key');

        $this->client = $fake;
        $this->categories = new Categories($fake, $this->decoder);
        $this->videos = new Videos($fake, $this->decoder);
        $this->searchResource = new Search($fake, $this->decoder);
        $this->channels = new Channels($fake, $this->decoder, $this->searchResource);
        $this->playlists = new Playlists($fake, $this->decoder);
        $this->playlistItems = new PlaylistItems($fake, $this->decoder);
        $this->activities = new Activities($fake, $this->decoder);
        $this->commentThreads = new CommentThreads($fake, $this->decoder);
    }
}
