# ⭐ YouTube API for Laravel

[![code-style](https://github.com/cable8mm/Youtube/actions/workflows/code-style.yml/badge.svg)](https://github.com/cable8mm/Youtube/actions/workflows/code-style.yml)
[![run-tests](https://github.com/cable8mm/Youtube/actions/workflows/run-tests.yml/badge.svg)](https://github.com/cable8mm/Youtube/actions/workflows/run-tests.yml)
[![Packagist Version](https://img.shields.io/packagist/v/cable8mm/youtube)](https://packagist.org/packages/cable8mm/youtube)
[![Packagist Downloads](https://img.shields.io/packagist/dt/cable8mm/youtube)](https://packagist.org/packages/cable8mm/youtube)
[![Packagist Stars](https://img.shields.io/packagist/stars/cable8mm/youtube)](https://packagist.org/packages/cable8mm/youtube)
[![PHP Version](https://img.shields.io/packagist/dependency-v/cable8mm/youtube/php)](https://packagist.org/packages/cable8mm/youtube)
[![Laravel Version](https://img.shields.io/packagist/dependency-v/cable8mm/youtube/illuminate/support)](https://packagist.org/packages/cable8mm/youtube)
[![License](https://img.shields.io/packagist/l/cable8mm/youtube)](https://packagist.org/packages/cable8mm/youtube)

> 🚀 A modern, elegant, and feature-rich Laravel wrapper for YouTube Data API v3 (Non-OAuth)

A beautifully crafted Laravel package that provides a simple, fluent interface to interact with YouTube Data API v3. Built with PHP 8.3+ features, comprehensive test coverage, and developer experience in mind.

## ✨ Features

- 🎯 **Simple & Elegant API** - Clean, intuitive, and Laravel-style interface
- ⚡ **Fluent Interface** - Chain methods for better readability
- 🔒 **Type-Safe** - Full type hints and strict typing for PHP 8.3+
- 🎨 **Laravel Native** - Seamless integration with Laravel ecosystem
- 🚀 **High Performance** - Built-in response caching to reduce API calls
- ✅ **Well Tested** - 73 comprehensive tests with 100% pass rate
- 🛡️ **Custom Exceptions** - Domain-specific error handling
- 📝 **Validation Rules** - Built-in validation for YouTube URLs (Laravel 12+)
- 🔄 **Auto-Discovery** - Automatic service provider registration
- 📚 **Extensive Documentation** - Clear examples and usage guides

## 📋 Requirements

- PHP 8.3 or higher
- Laravel 12.x or 13.x
- YouTube Data API v3 Key ([Get one here](https://console.developers.google.com))

## 🚀 Installation

Install the package via Composer:

```bash
composer require cable8mm/youtube
```

## ⚙️ Configuration

### Step 1: Publish Configuration

```bash
php artisan vendor:publish --provider="Cable8mm\Youtube\YoutubeServiceProvider"
```

### Step 2: Add API Key

Add your YouTube API key to your `.env` file:

```env
YOUTUBE_API_KEY=your_api_key_here
```

Or directly in `config/youtube.php`:

```php
return [
    'key' => env('YOUTUBE_API_KEY', 'YOUR_API_KEY'),
];
```

## 🎯 Quick Start

### Basic Usage

```php
use Cable8mm\Youtube\Facades\Youtube;

// Get video information
$video = Youtube::getVideoInfo('rie-hPVJ7Sw');

// Get multiple videos
$videos = Youtube::getVideoInfo(['rie-hPVJ7Sw', 'iKHTawgyKWQ']);

// Search videos
$results = Youtube::searchVideos('Laravel Tutorial', 10);

// Get channel information
$channel = Youtube::getChannelById('UCk1SpWNzOs4MYmr0uICEntg');

// Get popular videos by country
$popular = Youtube::getPopularVideos('US', 10);
```

### Fluent Interface

```php
$youtube = (new Youtube($apiKey))
    ->useHttpHost(true)
    ->cache()
    ->setCacheTtl(1800);

$videos = $youtube->getPopularVideos('US', 10);
```

### With Caching (Recommended)

Enable caching to reduce API calls and improve performance:

```php
// Via constructor
$youtube = new Youtube($apiKey, [
    'cache_enabled' => true,
    'cache_ttl' => 3600, // 1 hour
]);

// Or via fluent interface
$youtube = (new Youtube($apiKey))->cache()->setCacheTtl(3600);
```

## 📖 API Reference

### Video Methods

```php
// Get single video info
$video = Youtube::getVideoInfo('video_id');

// Get multiple videos
$videos = Youtube::getVideoInfo(['id1', 'id2']);

// Get localized video info
$video = Youtube::getLocalizedVideoInfo('video_id', 'ko');

// Get popular videos by region
$videos = Youtube::getPopularVideos('KR', 20);
```

### Search Methods

```php
// General search
$results = Youtube::search('Laravel', 10);

// Search videos only
$videos = Youtube::searchVideos('Laravel', 10, 'viewCount');

// Search in specific channel
$videos = Youtube::searchChannelVideos('keyword', 'channel_id', 20);

// Advanced search with custom parameters
$results = Youtube::searchAdvanced([
    'q' => 'Laravel',
    'type' => 'video',
    'part' => 'id,snippet',
    'maxResults' => 50,
    'order' => 'date'
], true); // true = include page info
```

### Channel Methods

```php
// Get channel by ID
$channel = Youtube::getChannelById('channel_id');

// Get channel by name
$channel = Youtube::getChannelByName('username');

// Get channel videos
$videos = Youtube::getChannelVideos('channel_id', 10, null, false, '');

// List channel videos
$videos = Youtube::listChannelVideos('channel_id', 10, 'date');
```

### Playlist Methods

```php
// Get playlist by ID
$playlist = Youtube::getPlaylistById('playlist_id');

// Get multiple playlists
$playlists = Youtube::getPlaylistById(['id1', 'id2']);

// Get playlists by channel
$playlists = Youtube::getPlaylistsByChannelId('channel_id');

// Get playlist items
$items = Youtube::getPlaylistItemsByPlaylistId('playlist_id', '', 50);
```

### Comment Methods

```php
// Get comment threads by video ID
$comments = Youtube::getCommentThreadsByVideoId('video_id', 20, 'time');
```

### Activity Methods

```php
// Get channel activities
$activities = Youtube::getActivitiesByChannelId('channel_id', 10);
```

### Utility Methods

```php
// Parse video ID from URL
$videoId = Youtube::parseVidFromURL('https://youtu.be/rie-hPVJ7Sw');

// Get channel from URL
$channel = Youtube::getChannelFromURL('https://youtube.com/channel/...');
```

## 🔍 Pagination

### Basic Pagination

```php
$params = [
    'q' => 'Laravel',
    'type' => 'video',
    'part' => 'id,snippet',
    'maxResults' => 50
];

// Get first page
$search = Youtube::searchAdvanced($params, true);

// Get next page
if (isset($search['info']['nextPageToken'])) {
    $params['pageToken'] = $search['info']['nextPageToken'];
    $nextPage = Youtube::searchAdvanced($params, true);
}
```

### Using paginateResults()

```php
$params = [
    'q' => 'Laravel',
    'type' => 'video',
    'part' => 'id,snippet',
    'maxResults' => 50
];

$pageTokens = [];

// Initial search
$search = Youtube::paginateResults($params, null);
$pageTokens[] = $search['info']['nextPageToken'];

// Navigate through pages
$page1 = Youtube::paginateResults($params, $pageTokens[0]);
$page2 = Youtube::paginateResults($params, $pageTokens[1]);

// Go back
$previousPage = Youtube::paginateResults($params, $pageTokens[0]);
```

## ✅ Validation Rules

Validate YouTube video URLs in your Laravel forms:

```php
use Cable8mm\Youtube\Rules\ValidYoutubeVideo;

$request->validate([
    'video_url' => ['bail', 'required', new ValidYoutubeVideo],
]);
```

**Supported URL formats:**

- `https://www.youtube.com/watch?v=VIDEO_ID`
- `https://youtu.be/VIDEO_ID`
- `https://www.youtube.com/embed/VIDEO_ID`

**Note:** Uses Laravel `ValidationRule` interface with closure-based error messages.

## 🔄 Channel Sync

Store channels in your database and keep their videos up to date with a queued job.

### Publish Config and Migrations

```bash
php artisan vendor:publish --provider="Cable8mm\Youtube\YoutubeServiceProvider"
php artisan vendor:publish --tag=youtube-migrations
php artisan migrate
```

This creates `youtube_channels`, `youtube_videos`, and `youtube_sync_errors`, with the models `YoutubeChannel`, `YoutubeVideo`, and `YoutubeSyncError` in `Cable8mm\Youtube\Models`.

Sync settings in `config/youtube.php`:

| Setting | Env | Default | Effect |
| --- | --- | --- | --- |
| `youtube.sync.queue` | `YOUTUBE_SYNC_QUEUE` | `null` | Queue for sync jobs. `null` uses your default queue. |
| `youtube.sync.max_pages` | | `1` | Pages of 50 uploads read per sync, unless `--full` is given. |

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

Accepted inputs: a Channel ID, a `@handle`, or a URL that starts with `/channel/`, `/@`, or `/user/`. Other inputs, including `/c/` URLs, fail with `unsupported input`.

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

Each sync adds new uploads, refreshes known videos, and sets `unavailable_at` on videos that became private or were deleted. No video is deleted.

### Sync Failures

A failed sync is not retried. It creates a `YoutubeSyncError` record, sets the channel's `sync_status` to `failed`, and stores the reason in `last_error_reason`.

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
| `ChannelSynced` | A sync finished | `$channel`, `$created`, `$updated`, `$unavailable` |
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

## 🧪 Testing

Run the test suite:

```bash
# Unit tests (no API key, no network; this is what CI runs)
composer test

# E2E tests against the live YouTube Data API (needs YOUTUBE_API_KEY in .env)
composer test:e2e
```

**Test Coverage:**

- ✅ 73 comprehensive tests
- ✅ Unit tests (no API key required)
- ✅ Integration tests with Orchestra Testbench (requires API key)
- ✅ 100% pass rate

## 📁 Package Structure

```
src/
├── Youtube.php                    # Main class
├── YoutubeServiceProvider.php     # Laravel service provider
├── Facades/
│   └── Youtube.php                # Laravel facade
├── Rules/
│   └── ValidYoutubeVideo.php      # Validation rule
├── Cache/
│   └── YoutubeCache.php           # Caching layer
├── Exceptions/
│   └── YoutubeApiException.php    # Custom exceptions
└── config/
    └── youtube.php                # Configuration file
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
