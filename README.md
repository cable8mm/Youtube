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

A beautifully crafted Laravel package that provides a simple, fluent interface to interact with YouTube Data API v3. Built with PHP 8.2+ features, comprehensive test coverage, and developer experience in mind.

## ✨ Features

- 🎯 **Simple & Elegant API** - Clean, intuitive, and Laravel-style interface
- ⚡ **Fluent Interface** - Chain methods for better readability
- 🔒 **Type-Safe** - Full type hints and strict typing for PHP 8.2+
- 🎨 **Laravel Native** - Seamless integration with Laravel ecosystem
- 🚀 **High Performance** - Built-in response caching to reduce API calls
- ✅ **Well Tested** - 64 comprehensive tests with 100% pass rate
- 🛡️ **Custom Exceptions** - Domain-specific error handling
- 📝 **Validation Rules** - Built-in validation for YouTube URLs
- 🔄 **Auto-Discovery** - Automatic service provider registration
- 📚 **Extensive Documentation** - Clear examples and usage guides

## 📋 Requirements

- PHP 8.2 or higher
- Laravel 8.x, 9.x, 10.x, 11.x, or 12.x
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

## 🧪 Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run with API tests enabled
YOUTUBE_ENABLED=true composer test
```

**Test Coverage:**

- ✅ 64 comprehensive tests
- ✅ Unit tests (no API key required)
- ✅ Integration tests (requires API key)
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

- 📚 [API Documentation](https://www.palgle.com/Youtube/)
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
