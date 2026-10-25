# ⭐ YouTube API for Laravel

[![code-style](https://github.com/cable8mm/Youtube/actions/workflows/code-style.yml/badge.svg)](https://github.com/cable8mm/Youtube/actions/workflows/code-style.yml)
[![run-tests](https://github.com/cable8mm/Youtube/actions/workflows/run-tests.yml/badge.svg)](https://github.com/cable8mm/Youtube/actions/workflows/run-tests.yml)
[![Packagist Version](https://img.shields.io/packagist/v/cable8mm/youtube)](https://packagist.org/packages/cable8mm/youtube)
[![Packagist Downloads](https://img.shields.io/packagist/dt/cable8mm/youtube)](https://packagist.org/packages/cable8mm/youtube)
[![Packagist Stars](https://img.shields.io/packagist/stars/cable8mm/youtube)](https://packagist.org/packages/cable8mm/youtube)
[![PHP Version](https://img.shields.io/packagist/dependency-v/cable8mm/youtube/php)](https://packagist.org/packages/cable8mm/youtube)
[![Laravel Version](https://img.shields.io/packagist/dependency-v/cable8mm/youtube/illuminate/support)](https://packagist.org/packages/cable8mm/youtube)
[![License](https://img.shields.io/packagist/l/cable8mm/youtube)](https://packagist.org/packages/cable8mm/youtube)

> 🚀 Keep YouTube channels and their videos in your Laravel database. Import channels from CSV or Excel, sync their videos with a scheduled queue job, and call the YouTube Data API v3 directly when you need to. API key only, no OAuth.

The package has two parts:

1. **Laravel channel sync** - Eloquent models and migrations, a channel import command, a queued video sync, failure records, a quota guard, events, and a validation rule for video URLs.
2. **API client** - one method per YouTube Data API v3 read operation, URL parsing, and response caching, for anything the sync does not cover.

## ✨ Features

- 🗄️ **Channel and video records** - Eloquent models and publishable migrations
- 📥 **Channel import** - CSV or Excel files with Channel IDs, `@handles`, or channel URLs
- 🔄 **Queued video sync** - adds new uploads, refreshes statistics, and flags videos that became private or were deleted
- 🛑 **Quota aware** - classifies sync failures and pauses syncing until the daily quota resets
- 📣 **Events** - `ChannelSynced`, `ChannelSyncFailed`, `QuotaExceeded`
- 📝 **Validation rule** - check that a submitted URL points to an existing YouTube video
- 🎯 **One method per API call** - videos, search, channels, playlists, playlist items, comment threads, activities, and categories
- 🔗 **URL parsing** - get a Video ID from watch, embed, short, mobile, YouTube Music, and privacy-enhanced (`youtube-nocookie.com`) URLs; resolve a channel from its URL
- 🚀 **Response caching** - optional, through your application's cache store
- 🔄 **Auto-Discovery** - the service provider and the `Youtube` facade register themselves

## 📋 Requirements

- PHP 8.3 or higher
- Laravel 12.x or 13.x
- A YouTube Data API v3 key ([get one here](https://console.developers.google.com))

[maatwebsite/excel](https://laravel-excel.com) is installed with the package and is used by the channel import.

## 🚀 Installation

```bash
composer require cable8mm/youtube
```

## ⚙️ Configuration

Publish the config file:

```bash
php artisan vendor:publish --provider="Cable8mm\Youtube\YoutubeServiceProvider"
```

Add your API key to `.env`:

```env
YOUTUBE_API_KEY=your_api_key_here
```

| Setting | Env | Default | Effect |
| --- | --- | --- | --- |
| `youtube.key` | `YOUTUBE_API_KEY` | `YOUR_API_KEY` | API key of the shared client. |
| `youtube.sync.queue` | `YOUTUBE_SYNC_QUEUE` | `null` | Queue for sync jobs. `null` uses your default queue. |
| `youtube.sync.max_pages` | | `1` | Pages of 50 uploads read per sync, unless `youtube:sync --full` is used. |

Publish and run the migrations for the channel sync tables:

```bash
php artisan vendor:publish --tag=youtube-migrations
php artisan migrate
```

## 🔄 Channel Sync

Store channels in your database and keep their videos up to date with a queued job. The usual flow:

1. [Import channels](#import-channels) from a CSV or Excel file.
2. [Schedule `youtube:sync`](#schedule-the-sync) and run a queue worker.
3. Read the [records](#records) in your app, and watch [failures](#sync-failures) and [events](#events).

### Import Channels

Make a CSV or Excel file (`csv`, `xlsx`, `xls`) with a `channel` column. Only the first sheet is read, and other columns are ignored.

```csv
channel
UCk1SpWNzOs4MYmr0uICEntg
@Google
https://www.youtube.com/channel/UCk1SpWNzOs4MYmr0uICEntg
https://www.youtube.com/@Google
https://www.youtube.com/user/Google
```

Accepted inputs: a Channel ID, a `@handle`, or a URL that starts with `/channel/`, `/@`, or `/user/`. Import never searches, so `/c/` URLs and plain names fail with `unsupported input`.

```bash
php artisan youtube:import-channels channels.csv
```

The command prints a count for each result (`created`, `updated`, `failed: <cause>`, `skipped: quota exceeded`) and one line per failed row:

```
created: 2
updated: 1
failed: unsupported input: 1
row 5: https://www.youtube.com/c/Google: unsupported input
```

It exits `1` when any row failed. Importing again updates channels and keeps their sync state and videos.

To attach the channels to one of your models, add the `HasYoutubeChannels` trait and import in code:

```php
use Cable8mm\Youtube\Concerns\HasYoutubeChannels;
use Cable8mm\Youtube\Imports\YoutubeChannelsImport;
use Maatwebsite\Excel\Facades\Excel;

class Artist extends Model
{
    use HasYoutubeChannels;
}

$import = new YoutubeChannelsImport($artist);
Excel::import($import, $path);

$import->results();          // [['row' => 2, 'input' => '@Google', 'result' => 'created'], ...]
$artist->youtubeChannels;    // the imported channels
```

### Schedule the Sync

`youtube:sync` queues one `SyncChannelVideos` job per active channel. Schedule it in `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('youtube:sync')->hourly();
```

Then run a queue worker:

```bash
php artisan queue:work --queue=default
```

Use the queue name you set in `YOUTUBE_SYNC_QUEUE` if it is not `default`.

```bash
php artisan youtube:sync                                      # every active channel
php artisan youtube:sync --channel=UCk1SpWNzOs4MYmr0uICEntg   # one channel, even if inactive
php artisan youtube:sync --full                               # read every page of uploads
```

Each sync reads the channel's newest uploads, then looks up those videos and the channel's stored videos together, 50 per request.

- New uploads are added, and stored videos get fresh titles and statistics.
- A stored video that the API no longer returns gets `unavailable_at` set. Private and deleted videos look the same to an API key, so they are not told apart.
- A video that comes back gets `unavailable_at` cleared.
- No video is deleted.

To stop syncing a channel, set `is_active` to `false`.

### Records

| Table | Model (`Cable8mm\Youtube\Models`) | Holds |
| --- | --- | --- |
| `youtube_channels` | `YoutubeChannel` | `channel_id`, `handle`, `title`, `uploads_playlist_id`, `is_active`, `sync_status`, `last_synced_at`, `last_error_reason`, optional owner |
| `youtube_videos` | `YoutubeVideo` | `video_id`, `title`, `description`, `published_at`, `duration`, `thumbnail_url`, view, like, and comment counts, `unavailable_at` |
| `youtube_sync_errors` | `YoutubeSyncError` | `reason`, `http_code`, `message` of each failed sync |

Relations: `$channel->videos`, `$channel->syncErrors`, `$channel->owner`, and `$video->channel`.

### Sync Failures

A failed sync is not retried. It creates a `YoutubeSyncError` record, sets the channel's `sync_status` to `failed`, and stores the reason in `last_error_reason`. Videos saved before the failure are kept.

| Failure Reason | When |
| --- | --- |
| `quota_exceeded` | The API quota is used up. |
| `invalid_key` | The API key is not valid. |
| `forbidden` | The API refused the request (HTTP 403). |
| `channel_not_found` | The channel or its uploads playlist no longer exists. |
| `transport` | A network error or an invalid response. |
| `unknown` | Anything else. |

**Quota Block:** a `quota_exceeded` failure starts a Quota Block that lasts until the next midnight in `America/Los_Angeles`, when the daily quota resets. During the block, `youtube:sync` queues nothing and prints `quota blocked until <time>`, and queued jobs send no request. The block is stored in your default cache store.

```php
use Cable8mm\Youtube\Sync\QuotaBlock;

$block = new QuotaBlock;
$block->isActive();   // bool
$block->until();      // ?Carbon
$block->clear();
```

### Events

All events are in `Cable8mm\Youtube\Events`.

| Event | Fired | Properties |
| --- | --- | --- |
| `ChannelSynced` | A sync finished | `$channel`, `$created`, `$updated`, `$unavailable` (newly flagged videos) |
| `ChannelSyncFailed` | A sync failed | `$channel`, `$reason`, `$exception` |
| `QuotaExceeded` | A Quota Block started | `$until` |

```php
use Cable8mm\Youtube\Events\ChannelSyncFailed;
use Illuminate\Support\Facades\Event;

Event::listen(function (ChannelSyncFailed $event) {
    logger()->warning("YouTube sync failed for {$event->channel->title}: {$event->reason}");
});
```

### Admin Screen

The package ships no admin screen. Build one in your app on top of the models. These examples are not dependencies of this package.

**Filament** resource:

```php
use Cable8mm\Youtube\Models\YoutubeChannel;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class YoutubeChannelResource extends Resource
{
    protected static ?string $model = YoutubeChannel::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable(),
            TextColumn::make('handle'),
            IconColumn::make('is_active')->boolean(),
            TextColumn::make('sync_status')->badge(),
            TextColumn::make('last_error_reason'),
            TextColumn::make('last_synced_at')->since(),
            TextColumn::make('videos_count')->counts('videos'),
        ]);
    }
}
```

**Nova** resource:

```php
use Cable8mm\Youtube\Models\YoutubeSyncError;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

class YoutubeSyncErrorResource extends Resource
{
    public static $model = YoutubeSyncError::class;

    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Channel', fn () => $this->channel?->title),
            Text::make('Reason'),
            Number::make('HTTP Code', 'http_code'),
            Textarea::make('Message'),
            DateTime::make('Created At')->sortable(),
        ];
    }
}
```

## ✅ Validation Rule

Check that a submitted URL points to an existing YouTube video:

```php
use Cable8mm\Youtube\Rules\ValidYoutubeVideo;

$request->validate([
    'video_url' => ['bail', 'required', new ValidYoutubeVideo],
]);
```

The rule accepts every video URL in [URL Parsing](#video-urls) and looks the video up with the shared client, or the client you pass: `new ValidYoutubeVideo($youtube)`.

| Input | Result |
| --- | --- |
| A URL of an existing video | passes |
| Not a string, or not a supported video URL | `The :attribute does not look like a valid Youtube URL.` (no API call) |
| A video that does not exist, or is private or deleted | `The :attribute does not exist on Youtube.` |
| The API call failed | `The :attribute could not be validated.` |

The rule never throws.

## 🎯 API Client

Use the client for anything the sync does not cover. It calls the YouTube Data API v3 directly and stores nothing.

```php
use Cable8mm\Youtube\Facades\Youtube;

// One video, or several at once
$video = Youtube::getVideoInfo('rie-hPVJ7Sw');
$videos = Youtube::getVideoInfo(['rie-hPVJ7Sw', 'iKHTawgyKWQ']);

// Search
$results = Youtube::searchVideos('Laravel Tutorial', 10);

// A channel by ID, @handle, or URL
$channel = Youtube::getChannelById('UCk1SpWNzOs4MYmr0uICEntg');
$channel = Youtube::getChannelByHandle('@Google');
$channel = Youtube::getChannelFromURL('https://www.youtube.com/@Google/videos');

// Popular videos in a country
$popular = Youtube::getPopularVideos('US', 10);
```

Results are the API's own resource objects (`stdClass`), not mapped or renamed. Read them as `$video->snippet->title`.

## 🔧 The Client

The facade, `app('youtube')`, and `app(\Cable8mm\Youtube\Youtube::class)` all return one shared client built from `youtube.key`. A setting changed on it applies to every later call in the same request.

To use another API key or settings, build your own client:

```php
use Cable8mm\Youtube\Youtube;

$youtube = new Youtube($apiKey, [
    'cache_enabled' => true,   // default false
    'cache_ttl' => 1800,       // seconds, default 3600
    'use-http-host' => true,   // send Referer: $_SERVER['HTTP_HOST'], default false
]);

// Or change settings fluently
$youtube = (new Youtube($apiKey))
    ->useHttpHost(true)
    ->cache()
    ->setCacheTtl(1800);
```

### Caching

With caching on, a response is stored in your default cache store and an identical call is served from the cache without a request. Error responses are never cached. When no cache store is bound, caching is skipped without an error.

## 📖 API Reference

### Videos

```php
$video  = Youtube::getVideoInfo('video_id');               // object or null
$videos = Youtube::getVideoInfo(['id1', 'id2']);           // array or null
$video  = Youtube::getLocalizedVideoInfo('video_id', 'ko');
$videos = Youtube::getPopularVideos('KR', 20);
```

### Search

Every search call costs 100 quota units.

```php
$results = Youtube::search('Laravel', 10);
$videos  = Youtube::searchVideos('Laravel', 10, 'viewCount');
$videos  = Youtube::searchChannelVideos('keyword', 'channel_id', 20);
$videos  = Youtube::listChannelVideos('channel_id', 10, 'date');

// Newest videos of a channel, published before a time
$page = Youtube::getChannelVideos('channel_id', 10, '2026-01-01T00:00:00Z');

// Any search.list parameters; true adds page info
$page = Youtube::searchAdvanced([
    'q' => 'Laravel',
    'type' => 'video',
    'part' => 'id,snippet',
    'maxResults' => 50,
    'order' => 'date',
], true);
```

### Channels

```php
$channel  = Youtube::getChannelById('channel_id');
$channels = Youtube::getChannelById(['id1', 'id2']);
$channel  = Youtube::getChannelByHandle('@Google');
$channel  = Youtube::getChannelByName('username');          // legacy /user/ name
$channel  = Youtube::searchChannelByName('Google');         // first search hit
$channel  = Youtube::getChannelFromURL('https://www.youtube.com/channel/UCk1SpWNzOs4MYmr0uICEntg');
```

### Playlists

```php
$playlist  = Youtube::getPlaylistById('playlist_id');
$playlists = Youtube::getPlaylistById(['id1', 'id2']);
$page      = Youtube::getPlaylistsByChannelId('channel_id');
$page      = Youtube::getPlaylistItemsByPlaylistId('playlist_id', '', 50);
```

### Comments, Activities, Categories

```php
$comments   = Youtube::getCommentThreadsByVideoId('video_id', 20, 'time');
$comments   = Youtube::getCommentThreads(channelId: 'channel_id');
$activities = Youtube::getActivitiesByChannelId('channel_id');
$categories = Youtube::getCategories('US');
```

### Result Values

| Call | Items found | Nothing found |
| --- | --- | --- |
| By ID with a string (`getVideoInfo('id')`) | one object | `null` |
| By ID with an array (`getVideoInfo([...])`) | array of objects | `null` |
| `getChannelByHandle`, `getChannelByName`, `searchChannelByName` | one object | `null` |
| Lists (`search`, `getPopularVideos`, ...) | array of objects | `false` |
| Lists with page info | `['results' => ..., 'info' => [...]]` | `results` is `false` |

`getPlaylistsByChannelId` and `getPlaylistItemsByPlaylistId` always return `['results' => ..., 'info' => ['totalResults', 'nextPageToken', 'prevPageToken']]`, with `false` for a missing token.

## 🔍 Pagination

Pass the `nextPageToken` from one page to get the next:

```php
$params = [
    'q' => 'Laravel',
    'type' => 'video',
    'part' => 'id,snippet',
    'maxResults' => 50,
];

$page = Youtube::paginateResults($params);

while ($page['info']['nextPageToken']) {
    $page = Youtube::paginateResults($params, $page['info']['nextPageToken']);
}
```

Playlist items work the same way:

```php
$page = Youtube::getPlaylistItemsByPlaylistId('playlist_id');

if ($page['info']['nextPageToken']) {
    $next = Youtube::getPlaylistItemsByPlaylistId('playlist_id', $page['info']['nextPageToken']);
}
```

No call reads more than one page by itself.

## 🔗 URL Parsing

### Video URLs

```php
Youtube::parseVidFromURL('https://youtu.be/rie-hPVJ7Sw');   // 'rie-hPVJ7Sw'
```

| URL | Video ID from |
| --- | --- |
| `https://www.youtube.com/watch?v=VIDEO_ID` | `v` |
| `https://m.youtube.com/watch?v=VIDEO_ID` | `v` |
| `https://music.youtube.com/watch?v=VIDEO_ID` | `v` |
| `https://www.youtube.com/embed/VIDEO_ID` | the path |
| `https://www.youtube-nocookie.com/embed/VIDEO_ID` | the path |
| `https://youtu.be/VIDEO_ID` | the path |

- The host decides, not the text: `https://example.com/?ref=youtube.com/watch?v=abc` is rejected.
- Other query parameters, such as `t`, `list`, or `feature=embed`, are ignored.
- A URL without `https://` works too: `youtube.com/watch?v=VIDEO_ID`.
- `/shorts/` and `/live/` URLs are not supported.
- An unsupported URL throws `InvalidArgumentException`.

### Channel URLs

`getChannelFromURL()` accepts `youtube.com`, `www.youtube.com`, and `m.youtube.com`. Trailing slashes and sub-pages such as `/videos` or `/about` are ignored.

| URL | Lookup | Quota |
| --- | --- | --- |
| `/channel/UC...` | `getChannelById` | 1 |
| `/@handle` | `getChannelByHandle` | 1 |
| `/user/name` | `getChannelByName` | 1 |
| `/c/name` or `/name` | `searchChannelByName` | 101 |

Non-channel pages such as `/feed/trending` or `/playlist` throw `InvalidArgumentException`.

## ⚠️ Errors

| When | Exception | Message |
| --- | --- | --- |
| `new Youtube('')` | `InvalidArgumentException` | `Google API key is Required, ...` |
| A search without `q`, `channelId`, or `videoCategoryId` | `InvalidArgumentException` | `at least the Search query or Channel ID or videoCategoryId must be supplied` |
| `getActivitiesByChannelId('')` | `InvalidArgumentException` | `ChannelId must be supplied` |
| An unsupported video or channel URL | `InvalidArgumentException` | `The supplied URL does not look like a Youtube URL` or `... Youtube Channel URL` |
| The request failed | `Cable8mm\Youtube\Exceptions\YoutubeApiException` | `Curl Error : ...` |
| The response is not a JSON object | `YoutubeApiException` | `Invalid response from Youtube API : ...` |
| The API returned an error | `YoutubeApiException` | `Error <code> <message> : <reason>`, with the HTTP code as the exception code |

The `InvalidArgumentException` cases are thrown before any request is sent. A response with no items is not an error. See [Result Values](#result-values).

## 🧪 Testing

```bash
# Unit tests (no API key, no network; this is what CI runs)
composer test

# E2E tests against the live YouTube Data API (needs YOUTUBE_API_KEY in .env)
composer test:e2e
```

- ✅ 223 unit tests with Orchestra Testbench (no API key, no network)
- ✅ 46 E2E tests against the live YouTube Data API (requires API key)

## 📁 Package Structure

```
src/
├── Youtube.php                    # The client
├── YoutubeServiceProvider.php     # Laravel service provider
├── Facades/
│   └── Youtube.php                # Laravel facade
├── Http/                          # API transport and response decoder
├── Resources/                     # One class per API resource group
├── Support/
│   └── UrlParser.php              # Video and channel URL parsing
├── Rules/
│   └── ValidYoutubeVideo.php      # Validation rule
├── Cache/
│   └── YoutubeCache.php           # Caching layer
├── Exceptions/
│   └── YoutubeApiException.php    # API exception
├── Models/                        # YoutubeChannel, YoutubeVideo, YoutubeSyncError
├── Concerns/
│   └── HasYoutubeChannels.php     # Owner model trait
├── Imports/
│   └── YoutubeChannelsImport.php  # CSV / Excel channel import
├── Console/                       # youtube:import-channels, youtube:sync
├── Jobs/
│   └── SyncChannelVideos.php      # Queued channel sync
├── Events/                        # ChannelSynced, ChannelSyncFailed, QuotaExceeded
├── Sync/                          # Failure reasons, Quota Block, channel input resolver
└── config/
    └── youtube.php                # Configuration file
database/
└── migrations/                    # Publishable migrations (youtube-migrations)
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the project
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 Changelog

See [CHANGELOG.md](CHANGELOG.md) for recent changes.

## 🔗 Links

- 📦 [Packagist](https://packagist.org/packages/cable8mm/youtube)
- 🐛 [Issue Tracker](https://github.com/cable8mm/Youtube/issues)
- 💻 [GitHub Repository](https://github.com/cable8mm/Youtube)

## 🙏 Credits

- Built on code from [Alaouy/youtube](https://github.com/alaouy/youtube)
- Inspired by the Laravel community

## 📄 License

This package is open-sourced software licensed under the [MIT license](LICENSE).

---

<div align="center">
  <p>Made with ❤️ by <a href="https://github.com/cable8mm">cable8mm</a></p>
  <p>
    <a href="https://github.com/cable8mm/Youtube">⭐ Star us on GitHub!</a>
  </p>
</div>
