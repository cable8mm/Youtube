<?php

/*
|--------------------------------------------------------------------------
| Laravel PHP Facade/Wrapper for the Youtube Data API v3
|--------------------------------------------------------------------------
|
| Here is where you can set your key for Youtube API. In case you do not
| have it, it can be acquired from: https://console.developers.google.com
*/

return [
    'key' => env('YOUTUBE_API_KEY', 'YOUR_API_KEY'),

    /*
    | Video sync: the queue for sync jobs (null uses the default queue) and
    | how many pages of 50 uploads a sync reads unless run with --full.
    */
    'sync' => [
        'queue' => env('YOUTUBE_SYNC_QUEUE'),
        'max_pages' => 1,
    ],
];
