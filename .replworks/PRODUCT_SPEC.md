# PRODUCT_SPEC: cable8mm/youtube

Reference sections as `PRODUCT_SPEC.md §N`. Terms in §2 are used with exactly those meanings.

## 1. Purpose

- The product is a Laravel package named `cable8mm/youtube`.
- The product is a read-only client for the YouTube Data API v3. It authenticates with an API Key only. It has no OAuth.
- The product provides URL parsing for YouTube video and channel URLs.
- The product provides a validation rule that checks a URL points to an existing YouTube video.
- The product stores YouTube channels and their videos in the host application's database (§16).
- The product imports channels from a CSV or Excel file (§17).
- The product keeps stored videos up to date with queued sync jobs that the host application schedules (§18).
- The product records sync failures, classifies their cause, and stops syncing for the day when the API quota is exhausted (§19).

## 2. Definitions

- Developer: a person writing an application that uses the product.
- API Key: a YouTube Data API v3 key. Every request sends it as the `key` query parameter.
- Client: an instance of `Cable8mm\Youtube\Youtube`.
- Shared Client: the single Client the service container returns (§5).
- Transport: the instance of `Cable8mm\Youtube\Http\ApiClient` owned by a Client. It holds the API Key and request settings.
- Part: the list of resource parts requested from the API. It is sent comma-joined as the `part` query parameter.
- Resource Object: one decoded item from the `items` array of an API response, unchanged.
- Page Info: an array describing pagination of a response (§6).
- Video ID: the identifier of a YouTube video, for example `rie-hPVJ7Sw`.
- Video URL Error: `InvalidArgumentException` with message `The supplied URL does not look like a Youtube URL`.
- Channel URL Error: `InvalidArgumentException` with message `The supplied URL does not look like a Youtube Channel URL`.
- Result types: Single Result, Multi Result, By-ID Result, List Result, Paged Result, Normalized Paged Result (§6).
- Channel ID: the identifier of a YouTube channel: `UC` followed by 22 characters from `A-Z`, `a-z`, `0-9`, `-`, `_`. Example: `UCk1SpWNzOs4MYmr0uICEntg`.
- Handle: a channel's `@` name, for example `@Google`.
- Channel Record: one row of the `youtube_channels` table (§16.1).
- Video Record: one row of the `youtube_videos` table (§16.2).
- Sync Error Record: one row of the `youtube_sync_errors` table (§16.3).
- Owner: any Eloquent model of the host application that a Channel Record belongs to. A Channel Record may have no Owner.
- Sync: one run of the sync job for one Channel Record (§18.2).
- Failure Reason: one of the values in §19.1.
- Quota Block: the state in which no Sync sends a request (§19.3).

## 3. Goals

- The Developer configures the API Key once, through `config/youtube.php` and the `YOUTUBE_API_KEY` environment variable.
- The Developer calls one method per YouTube Data API v3 read operation and receives Resource Objects.
- The Developer reduces API quota use with optional response caching (§10).
- The Developer validates submitted video URLs (§11).
- An administrator registers many channels at once by uploading a CSV or Excel file (§17).
- Stored videos follow the channel: new uploads are added, statistics are refreshed, and videos that disappear are flagged, not deleted (§18).
- An administrator sees why a channel failed to sync and when quota will allow syncing again (§19).
- The administration screen is built by the host application on top of the Channel, Video, and Sync Error Records. The product ships no screen (§14).

## 4. Installation and configuration

- `composer require cable8mm/youtube` installs the product.
- Laravel package discovery registers `Cable8mm\Youtube\YoutubeServiceProvider` and the facade alias `Youtube`.
- `php artisan vendor:publish --provider="Cable8mm\Youtube\YoutubeServiceProvider"` publishes `config/youtube.php`.
- `php artisan vendor:publish --tag=youtube-migrations` publishes the migrations for the tables in §16. The product does not run migrations the host has not published.
- Settings:

| Setting | Environment variable | Default | Effect |
| --- | --- | --- | --- |
| `youtube.key` | `YOUTUBE_API_KEY` | `YOUR_API_KEY` | The API Key of the Shared Client. |
| `youtube.sync.queue` | `YOUTUBE_SYNC_QUEUE` | `null` | Queue name for sync jobs. `null` uses the host's default queue. |
| `youtube.sync.max_pages` | none | `1` | Pages of 50 uploads a Sync reads unless `youtube:sync` runs with `--full` (§18.1). |

- Caching and the Referer header are not settings; they are set on a Client (§5).

## 5. Client construction and settings

- The service container returns one Shared Client per application. It is built on first resolution with `youtube.key` as its API Key.
- The `Youtube` facade, the container key `youtube`, and the container key `Cable8mm\Youtube\Youtube` all return the Shared Client.
- `new Youtube(string $youtube_key, array $config = [])` builds a separate Client.
- An empty `$youtube_key` throws the error in §12 row 1.
- `$config` keys:

| Key | Type | Default | Effect |
| --- | --- | --- | --- |
| `use-http-host` | bool | `false` | When true and `$_SERVER['HTTP_HOST']` is set, every request sends header `Referer: <$_SERVER['HTTP_HOST']>`. |
| `cache_enabled` | bool | `false` | Turns on response caching (§10). |
| `cache_ttl` | int, seconds | `3600` | Cache lifetime for stored responses. |

- These methods change the Client in place and return the same Client: `setApiKey(string)`, `useHttpHost(bool)`, `cache(bool = true)`, `setCacheTtl(int)`.
- `getApiKey()` returns the current API Key.
- `getClient()` returns the Transport. The Transport exposes `isUsingHttpHost()`, `isCacheEnabled()`, and `getCacheTtl()`.
- A change made on the Shared Client is seen by every later use of the Shared Client in the same process.
- `setApiKey('')` is accepted; only the constructor rejects an empty key.

## 6. Result types

| Result type | Value when the response has items | Value when the response has no items |
| --- | --- | --- |
| Single Result | the first Resource Object | `null` |
| Multi Result | array of Resource Objects | `null` |
| By-ID Result | Single Result when the ID argument is a string; Multi Result when it is an array | as the chosen type |
| List Result | array of Resource Objects | `false` |
| Paged Result | `['results' => List Result, 'info' => Page Info]` | `results` is `false` |
| Normalized Paged Result | `['results' => List Result, 'info' => ['totalResults' => int, 'nextPageToken' => string, 'prevPageToken' => string]]` | `results` is `false` |

Page Info keys:

- Always present: `kind`, `etag`, `prevPageToken`, `nextPageToken`. A value missing from the response is `null`.
- Present only when the response has `pageInfo`: `resultsPerPage`, `totalResults`.

Normalized Paged Result `info` defaults: `totalResults` is `0`, `nextPageToken` is `false`, and `prevPageToken` is `false` when the response lacks the value.

Resource Objects are never mapped, renamed, or filtered.

## 7. API operations

### 7.1 Operations

Every operation sends one GET request, except `searchChannelByName` and `getChannelFromURL`, which may send two. Every request also sends `key`. "Sent parameters" lists the query parameters in addition to `key`; `[x]` means sent only under the rule in §7.2.

| Method | Endpoint | Sent parameters | Default Part | Returns |
| --- | --- | --- | --- | --- |
| `getVideoInfo(array\|string $vId, array $part)` | videos | `id`, `part` | `id, snippet, contentDetails, player, statistics, status` | By-ID Result |
| `getLocalizedVideoInfo(array\|string $vId, string $language, array $part)` | videos | `id`, `hl`=`$language`, `part` | same as `getVideoInfo` | By-ID Result |
| `getPopularVideos(string $regionCode, int $maxResults = 10, array $part)` | videos | `chart`=`mostPopular`, `part`, `regionCode`, `maxResults` | same as `getVideoInfo` | List Result |
| `search(string $q, int $maxResults = 10, array $part)` | search | `q`, `part`, `maxResults` | `id, snippet` | List Result |
| `searchVideos(string $q, int $maxResults = 10, ?string $order = null, array $part)` | search | `q`, `type`=`video`, `part`, `maxResults`, [`order`] | `id` | List Result |
| `searchChannelVideos(string $q, string $channelId, int $maxResults = 10, ?string $order = null, array $part, bool $pageInfo = false)` | search | `q`, `type`=`video`, `channelId`, `part`, `maxResults`, [`order`] | `id, snippet` | List Result; Paged Result when `$pageInfo` |
| `listChannelVideos(string $channelId, int $maxResults = 10, ?string $order = null, array $part, bool $pageInfo = false)` | search | `type`=`video`, `channelId`, `part`, `maxResults`, [`order`] | `id, snippet` | List Result; Paged Result when `$pageInfo` |
| `getChannelVideos(string $channelId, int $maxResults, ?string $publishedBefore, bool $isFuture = false, string $pageToken = '')` | search | `type`=`video`, `channelId`, `part`=`id,snippet`, `maxResults`, `order`=`date`, [`publishedBefore` or `publishedAfter`], [`pageToken`] | fixed `id,snippet` | Paged Result |
| `searchAdvanced(array $params, bool $pageInfo = false)` | search | `$params` unchanged | none | List Result; Paged Result when `$pageInfo` |
| `paginateResults(array $params, ?string $token = null)` | search | `$params`, [`pageToken`=`$token`] | none | Paged Result |
| `getChannelById(array\|string $id, array $optionalParams = [], array $part)` | channels | `id`, `part`, `$optionalParams` | `id, snippet, contentDetails, statistics` | By-ID Result |
| `getChannelByName(string $username, array $optionalParams = [], array $part)` | channels | `forUsername`, `part`, `$optionalParams` | same as `getChannelById` | Single Result |
| `getChannelByHandle(string $handle, array $optionalParams = [], array $part)` | channels | `forHandle`, `part`, `$optionalParams` | same as `getChannelById` | Single Result |
| `searchChannelByName(string $username, int $maxResults = 1, array $part)` | search, then channels | search: `q`, `part`, `type`=`channel`, `maxResults` | `id, snippet` for the search | Single Result of `getChannelById(<first hit's snippet.channelId>)`; `null` when the search has no hit and no second request is sent |
| `getChannelFromURL(string $youtube_url)` | see §9 | see §9 | see §9 | see §9 |
| `getPlaylistById(array\|string $id, array $part)` | playlists | `id`, `part` | `id, snippet, status` | By-ID Result |
| `getPlaylistsByChannelId(string $channelId, array $optionalParams = [], array $part)` | playlists | `channelId`, `part`, `$optionalParams` | `id, snippet, status` | Normalized Paged Result |
| `getPlaylistItemsByPlaylistId(string $playlistId, string $pageToken = '', int $maxResults = 50, array $part)` | playlistItems | `playlistId`, `part`, `maxResults`, `pageToken` | `id, snippet, contentDetails, status` | Normalized Paged Result |
| `getCommentThreads(?string $channelId = null, ?string $id = null, ?string $videoId = null, int $maxResults = 20, ?string $order = null, array $part, bool $pageInfo = false)` | commentThreads | [`channelId`], [`id`], [`videoId`], [`maxResults`], [`part`], [`order`] | `id, replies, snippet` | List Result; Paged Result when `$pageInfo` |
| `getCommentThreadsByVideoId(?string $videoId = null, int $maxResults = 20, ?string $order = null, array $part, bool $pageInfo = false)` | commentThreads | as `getCommentThreads(null, null, $videoId, ...)` | `id, replies, snippet` | List Result; Paged Result when `$pageInfo` |
| `getActivitiesByChannelId(string $channelId, array $part, int $maxResults = 5, bool $pageInfo = false, string $pageToken = '')` | activities | `channelId`, `part`, `maxResults`, `pageToken` | `id, snippet, contentDetails` | List Result; Paged Result when `$pageInfo` |
| `getCategories(string $regionCode = 'US', array $part)` | videoCategories | `part`, `regionCode` | `snippet` | Multi Result |

### 7.2 Parameter rules

- `order` is sent only when `$order` is not empty.
- `$optionalParams` is merged after the generated parameters; a key in `$optionalParams` replaces the generated value.
- `getCommentThreads` drops every parameter whose value is empty (`null`, `''`, `0`) before sending.
- `pageToken` is always sent by `getPlaylistItemsByPlaylistId` and `getActivitiesByChannelId`, including when it is `''`.
- `searchAdvanced` (and every operation that calls it: `search`, `searchVideos`, `searchChannelVideos`, `listChannelVideos`, `getChannelVideos`, `paginateResults`, `searchChannelByName`) throws §12 row 2 before any request when `$params` has none of `q`, `channelId`, `videoCategoryId`.
- `getActivitiesByChannelId` throws §12 row 3 before any request when `$channelId` is empty.

### 7.3 Channel video window

`getChannelVideos` time bounds:

| `$publishedBefore` | `$isFuture` | Sent bound |
| --- | --- | --- |
| `null` | any | none |
| time string | `false` | `publishedBefore` = the time minus 1 second, RFC 3339 |
| time string | `true` | `publishedAfter` = the time plus 1 second, RFC 3339 |

`pageToken` is sent only when `$pageToken` is not empty.

## 8. Video URL parsing

`Youtube::parseVidFromURL(string $youtube_url): string` is static. It sends no request and needs no API Key.

Rules apply in this order; the first matching rule decides:

1. The URL string contains `youtube.com` and contains `embed`: the result is the URL path with its first 7 characters (`/embed/`) removed.
2. The URL string contains `youtube.com`: the result is the URL-decoded `v` query parameter.
3. The URL string contains `youtu.be`: the result is the URL path with its leading `/` removed.
4. Otherwise: throw the Video URL Error.

After the rule, a result that is not a string, or is `''`, throws the Video URL Error.

Consequences: `youtube.com/shorts/{id}` and `youtube.com/live/{id}` have no `v` parameter and throw. Query strings on embed and `youtu.be` URLs are ignored.

### 8.1 Open gap [UNVERIFIED]

- Rule 1 matches `embed` anywhere in the URL string. `https://www.youtube.com/watch?v=abc&feature=embed` throws the Video URL Error instead of returning `abc`.
- Rules 1-3 match `youtube.com` and `youtu.be` anywhere in the URL string, not only in the host.
- Whether these are intended is undecided.

## 9. Channel URL resolution

`getChannelFromURL(string $youtube_url)` returns a Single Result for a channel. Let `path` be the URL path, and `last` be the text after the final `/` of `path`.

Rules apply in this order; the first matching rule decides:

1. The URL string does not contain `youtube.com`: throw the Video URL Error.
2. `path` starts with `/channel/`: return `getChannelById(last)`.
3. `path` starts with `/user/`: return `getChannelByName(last)`.
4. `path` starts with `/c/`: return `searchChannelByName(last)`.
5. `path` starts with `/@`: return `searchChannelByName(last with every '@' removed)`.
6. `path` contains, anywhere, one of these reserved patterns: throw the Channel URL Error.
   - whole word after `/`: `about`, `account`, `ads`, `creators`, `feed`, `gaming`, `howyoutubeworks`, `new`, `playlist`, `results`, `shorts`, `upload`
   - prefix: `/account_`, `/reporthistory`
   - with a following `/`: `/feed/`, `/gaming/`, `/howyoutubeworks/`, `/playlist/`, `/shorts/`, `/t/`, `/yt/`
7. The first segment of `path` is empty: throw the Channel URL Error.
8. Otherwise: return `searchChannelByName(<first segment of path>)`.

Observed: `https://www.youtube.com/@Google` and `https://www.youtube.com/user/Google` both return channel `UCK8sQmJBp8GCxrOtXWBpyEA`.

### 9.1 Open gap [UNVERIFIED]

- Rules 2-5 use `last`, so a channel sub-page is misread: `/channel/UC1/videos` requests channel ID `videos`, and `/@xda/videos` searches for `videos`.
- `/channel/UC1/` (trailing slash) requests an empty channel ID.
- Whether these are intended is undecided.

## 10. Response caching

- Caching is off by default. It is on when `cache_enabled` is true or after `cache()` (§5).
- Caching applies only when the host application has a bound `cache` service. Otherwise it is skipped without error.
- The store is the host application's default cache store.
- Cache key: `youtube_api_` + MD5 of (endpoint URL + query string of all sent parameters including `key`, sorted by parameter name).
- Before a request: when the key exists in the store, its value is returned and no request is sent.
- After a request: the response body is stored for the Client's TTL only when it decodes to a JSON object that has no `error` member.
- `Cable8mm\Youtube\Cache\YoutubeCache` is a public standalone wrapper over a cache repository with methods `remember`, `get`, `put`, `has`, `forget`, `clear`, `setTtl`, and `generateKey(string $url, array $params)`. `generateKey` produces the cache key format above.

## 11. Validation rule

`new Cable8mm\Youtube\Rules\ValidYoutubeVideo(?Youtube $youtube = null)` is a Laravel validation rule.

- The lookup Client is `$youtube` when given, otherwise the Shared Client.
- The lookup calls `getVideoInfo(<Video ID>, ['id'])`.

| Input | Outcome |
| --- | --- |
| a string that §8 parses, and the lookup returns a Resource Object | pass |
| a string that §8 parses, and the lookup returns `null` | fail: `The :attribute does not exist on Youtube.` |
| a string or int that §8 rejects | fail: `The :attribute could not be validated.` |
| any input whose lookup throws an `Exception` | fail: `The :attribute could not be validated.` |

### 11.1 Open gap [UNVERIFIED]

- `null`, arrays, and objects reach `parseVidFromURL(string)` and throw an uncaught `TypeError` instead of failing validation.
- The message `The :attribute does not look like a valid Youtube URL.` is defined in the rule but never produced, because §8 throws instead of returning an empty Video ID.
- Whether these are intended is undecided.

## 12. Errors

| Row | Condition | Raised before a request | Exception | Message | Code |
| --- | --- | --- | --- | --- | --- |
| 1 | constructor `$youtube_key` is empty | yes | `InvalidArgumentException` | `Google API key is Required, please visit https://console.developers.google.com/` | 0 |
| 2 | search parameters lack `q`, `channelId`, and `videoCategoryId` (§7.2) | yes | `InvalidArgumentException` | `at least the Search query or Channel ID or videoCategoryId must be supplied` | 0 |
| 3 | `getActivitiesByChannelId` with empty `$channelId` | yes | `InvalidArgumentException` | `ChannelId must be supplied` | 0 |
| 4 | §8 or §9 rejects a URL | yes | `InvalidArgumentException` | Video URL Error or Channel URL Error | 0 |
| 5 | the transport fails | no | `Cable8mm\Youtube\Exceptions\YoutubeApiException` | `Curl Error : <transport error>` | 0 |
| 6 | the body does not decode to a JSON object | no | `YoutubeApiException` | `Invalid response from Youtube API : <JSON decoder error>`; the decoder error is `No error` when the body is valid JSON but not an object | 0 |
| 7 | the body has an `error` member | no | `YoutubeApiException` | `Error <error.code> <error.message>`, then ` : <error.errors[0].reason>` when that reason exists | `error.code` |

- Observed row 7 example (invalid API Key): message `Error 400 API key not valid. Please pass a valid API key. : badRequest`, code `400`.
- A response with no items is not an error; it yields the empty value of the result type (§6).
- `YoutubeApiException::__construct(string $message = '', int $code = 0, ?Throwable $previous = null)` keeps `$previous`.

## 13. Low-level helpers

Public methods on the Client kept for backward compatibility:

| Method | Behavior |
| --- | --- |
| `getApi(string $name)` | Returns the endpoint URL for `$name`. Names: `categories.list`, `videos.list`, `search.list`, `channels.list`, `playlists.list`, `playlistItems.list`, `activities`, `commentThreads.list`. |
| `api_get(string $url, array $params): string` | Sends one GET to `$url` with `$params` plus `key`, applying §5 headers and §10 caching. Returns the raw body. |
| `decodeSingle(string &$apiData)` | Applies Single Result to the body; raises §12 rows 6-7. |
| `decodeMultiple(string &$apiData)` | Applies Multi Result to the body; raises §12 rows 6-7. |
| `decodeList(string &$apiData)` | Applies List Result to the body; raises §12 rows 6-7; records the Page Info on the Client. |
| `static _parse_url_path(string $url): string` | Returns the URL path, or `''` when there is none. |
| `static _parse_url_query(string $url): array` | Returns the URL-decoded query parameters, or `[]` when there is no query. |

## 14. Non-goals

- No OAuth. No write operations to YouTube (insert, update, delete, upload).
- The API operations in §7 do not map Resource Objects to typed objects, models, or storage. Only Sync (§18) and channel import (§17) write records.
- No API operation in §7 fetches more than one page per call.
- No retries and no rate limiting. The only quota handling is the Quota Block (§19.3).
- No administration screen. The product ships no Filament, Nova, or other UI resources.
- No `search.list` request during import or Sync. `/c/` URLs and plain search terms are not accepted by import (§17.2).
- No schedule is registered by the product. The host application schedules `youtube:sync` (§18.1).
- Video Records are never deleted by the product.

## 15. Acceptance criteria

- With a valid API Key, every operation in §7.1 returns its listed result type.
- `getVideoInfo('rie-hPVJ7Sw')` returns a Resource Object with `id` and `snippet`; a nonexistent Video ID returns `null`.
- `parseVidFromURL` returns `rie-hPVJ7Sw` for `https://www.youtube.com/watch?v=rie-hPVJ7Sw`, `https://youtu.be/rie-hPVJ7Sw`, and `https://www.youtube.com/embed/rie-hPVJ7Sw`.
- With caching on, two identical calls send one request; a response matching §12 row 6 or 7 is never served from the cache.
- An invalid API Key raises §12 row 7 with code `400`.
- The validation rule produces each outcome in §11.

## 16. Storage

Each table below is created by a published migration (§4). Each table has an Eloquent model. Timestamps `created_at` and `updated_at` exist on every table.

### 16.1 `youtube_channels`

Model: `Cable8mm\Youtube\Models\YoutubeChannel`.

| Column | Type | Rule |
| --- | --- | --- |
| `id` | big integer, primary key | |
| `channel_id` | string | Channel ID. Unique. |
| `handle` | string, nullable | The channel's `snippet.customUrl`, for example `@google`. |
| `title` | string | `snippet.title`. |
| `uploads_playlist_id` | string, nullable | `contentDetails.relatedPlaylists.uploads`. |
| `owner_type`, `owner_id` | nullable morph | The Owner. |
| `is_active` | boolean, default `true` | `youtube:sync` without `--channel` syncs only active Channel Records. |
| `sync_status` | string, nullable | `null` before the first Sync, then `ok` or `failed` (§18.2, §19.2). |
| `last_synced_at` | timestamp, nullable | Time of the last successful Sync. |
| `last_error_reason` | string, nullable | Failure Reason of the last failed Sync; `null` after a successful Sync. |

Relations: `videos()` has many Video Records; `syncErrors()` has many Sync Error Records; `owner()` morphs to the Owner.

### 16.2 `youtube_videos`

Model: `Cable8mm\Youtube\Models\YoutubeVideo`.

| Column | Type | Source in the videos Resource Object |
| --- | --- | --- |
| `id` | big integer, primary key | |
| `youtube_channel_id` | foreign key to `youtube_channels.id`, cascade on delete | |
| `video_id` | string, unique | `id` |
| `title` | string | `snippet.title` |
| `description` | text, nullable | `snippet.description` |
| `published_at` | timestamp | `snippet.publishedAt` |
| `duration` | string, nullable | `contentDetails.duration`, ISO 8601 as returned, for example `PT4M13S` |
| `thumbnail_url` | string, nullable | `snippet.thumbnails.high.url`, else `snippet.thumbnails.default.url` |
| `view_count`, `like_count`, `comment_count` | unsigned big integer, nullable | `statistics.viewCount`, `statistics.likeCount`, `statistics.commentCount`; `null` when the member is absent |
| `unavailable_at` | timestamp, nullable | §18.3 |

Relation: `channel()` belongs to the Channel Record.

### 16.3 `youtube_sync_errors`

Model: `Cable8mm\Youtube\Models\YoutubeSyncError`.

| Column | Type | Rule |
| --- | --- | --- |
| `id` | big integer, primary key | |
| `youtube_channel_id` | foreign key to `youtube_channels.id`, nullable, null on delete | The Channel Record whose Sync failed. |
| `reason` | string | Failure Reason (§19.1). |
| `http_code` | integer, nullable | The exception code when it is not `0`. |
| `message` | text | The exception message. |

Relation: `channel()` belongs to the Channel Record.

### 16.4 Owner trait

`Cable8mm\Youtube\Concerns\HasYoutubeChannels` is a trait for an Owner model. It adds `youtubeChannels()`, a morph-many relation to Channel Records.

## 17. Channel import

### 17.1 Entry points

- `php artisan youtube:import-channels {file}` imports `{file}`. Accepted extensions: `csv`, `xlsx`, `xls`.
- `new Cable8mm\Youtube\Imports\YoutubeChannelsImport(?Model $owner = null)` is an import object for `Maatwebsite\Excel\Facades\Excel::import()`. When `$owner` is given, every Channel Record it creates or updates gets that Owner. The command passes no Owner.
- Only the first sheet is read. The first row is the header row. The column named `channel` is required; other columns are ignored. A file without a `channel` header imports nothing and reports `missing channel column`.
- Rows are processed in order, synchronously, with the Shared Client.

### 17.2 Channel input resolution

Each `channel` cell, trimmed, is resolved to a channels Resource Object with Part `id, snippet, contentDetails`. Rules apply in this order; the first match decides:

| Input | Request |
| --- | --- |
| a Channel ID | `getChannelById(<input>)` |
| starts with `@` | `getChannelByHandle(<input>)` |
| a URL whose path starts with `/channel/` | `getChannelById(<first path segment after /channel/>)` |
| a URL whose path starts with `/@` | `getChannelByHandle(<first path segment>)` |
| a URL whose path starts with `/user/` | `getChannelByName(<first path segment after /user/>)` |
| anything else, including `/c/` URLs and empty cells | no request; row result `failed: unsupported input` |

A request that returns `null` gives the row result `failed: channel not found`.

### 17.3 Writing

- A resolved channel is written by `channel_id`: a new Channel Record is created, or the existing one is updated. Existing `is_active`, `sync_status`, and Video Records are kept.
- Written columns: `channel_id`, `handle`, `title`, `uploads_playlist_id`, and the Owner when one is given.
- Row results: `created`, `updated`, `failed: <cause>`.
- A `YoutubeApiException` on a row gives `failed: <Failure Reason>` (§19.1). When the Failure Reason is `quota_exceeded`, the remaining rows are not processed and each gets `skipped: quota exceeded`.
- After the run, the command prints the count of each row result and one line per failed row: `row <n>: <cell value>: <cause>`, where `<n>` is the sheet row number. The command exits `0` when no row failed and `1` otherwise.
- The import object exposes the same row results through `results()`, a list of `['row' => int, 'input' => string, 'result' => string]`.

## 18. Video sync

### 18.1 Command

- `php artisan youtube:sync {--channel=*} {--full}` dispatches one `Cable8mm\Youtube\Jobs\SyncChannelVideos` job per Channel Record, on queue `youtube.sync.queue`.
- Without `--channel`, every active Channel Record is dispatched. With `--channel`, each given Channel ID is dispatched whether active or not; an unknown Channel ID prints `unknown channel <id>` and is skipped.
- Each job gets `$maxPages` = `youtube.sync.max_pages`, or `null` with `--full`.
- During a Quota Block the command dispatches nothing, prints `quota blocked until <time>`, and exits `0`.
- The host application schedules the command, for example `Schedule::command('youtube:sync')->hourly()`.

### 18.2 Sync job

`SyncChannelVideos(int $youtubeChannelId, ?int $maxPages = 1)` runs once. It is not retried. Steps:

1. During a Quota Block, end without a request and without changing any record.
2. When `uploads_playlist_id` is `null`, fetch the channel with `getChannelById` and store `uploads_playlist_id`, `handle`, and `title`. When the lookup returns `null`, the Sync fails with Failure Reason `channel_not_found` and exception `YoutubeApiException` with message `Channel not found : <channel_id>` and code `0`.
3. Read the uploads playlist with `getPlaylistItemsByPlaylistId(<uploads_playlist_id>, <pageToken>, 50)`: at most `$maxPages` pages, or every page when `$maxPages` is `null`. Collect each item's `contentDetails.videoId`.
4. Let the check set be the collected Video IDs plus the Video IDs of every Video Record of the channel.
5. Call `getVideoInfo(<up to 50 IDs>, ['id', 'snippet', 'contentDetails', 'statistics'])` for each batch of the check set.
6. Each returned Resource Object creates or updates its Video Record by `video_id` with the columns in §16.2, and sets `unavailable_at` to `null`.
7. Apply §18.3 to the Video Records not returned.
8. Set `sync_status` = `ok`, `last_synced_at` = now, `last_error_reason` = `null`, and fire `ChannelSynced` (§20).

Any exception in steps 2-7 stops the Sync and is handled by §19.2. The job does not rethrow it.

### 18.3 Unavailable videos

- A Video Record in the check set that `getVideoInfo` does not return gets `unavailable_at` = now when it is `null`. An already-set `unavailable_at` is kept.
- A Video Record returned again gets `unavailable_at` = `null`.
- The product does not distinguish deleted from private videos.
- Observed 2026-10-25: `videos.list` with an API Key and `id`=`rie-hPVJ7Sw,3x_bMyRvJu0,Flf-L9FjaV8` returns only `rie-hPVJ7Sw`, with `pageInfo` `{"totalResults":1,"resultsPerPage":1}`. Private video `3x_bMyRvJu0` alone and deleted video `Flf-L9FjaV8` alone each return no `items` and `pageInfo` `{"totalResults":0,"resultsPerPage":0}`, with no `error` member. Deleted and private videos are therefore indistinguishable.

## 19. Sync failures

### 19.1 Failure Reasons

The first matching row decides. "reason" is `error.errors[0].reason` of the API error body.

| Failure Reason | Condition | Source |
| --- | --- | --- |
| `quota_exceeded` | reason is `quotaExceeded`, `dailyLimitExceeded`, or `rateLimitExceeded` | observed: code `429`, reason `rateLimitExceeded`, message containing `Quota exceeded for quota metric 'Search Queries'`; documented: code `403`, reason `quotaExceeded` |
| `invalid_key` | code `400` and the message contains `API key not valid` | observed (PS §12 row 7 example) |
| `forbidden` | code `403` and no earlier row matched | documented |
| `channel_not_found` | reason is `playlistNotFound` or `channelNotFound`; also assigned by §18.2 step 2 | documented |
| `transport` | the message starts with `Curl Error : ` or `Invalid response from Youtube API : ` (§12 rows 5-6) | §12 |
| `unknown` | anything else | |

`Cable8mm\Youtube\Sync\FailureReason::fromThrowable(Throwable $e): string` returns the Failure Reason of an exception. `channel_not_found` for a `null` lookup (§18.2 step 2) is assigned by the job, not by this method.

### 19.2 Recording a failed Sync

1. Create a Sync Error Record with the Failure Reason, the exception code (`null` when `0`), and the exception message.
2. Set the Channel Record's `sync_status` = `failed` and `last_error_reason` = the Failure Reason. `last_synced_at` is unchanged.
3. When the Failure Reason is `quota_exceeded`, start a Quota Block (§19.3) and fire `QuotaExceeded`.
4. Fire `ChannelSyncFailed`.

Records written before the failure are kept.

### 19.3 Quota Block

- A Quota Block lasts until the next midnight in `America/Los_Angeles`, when the YouTube Data API daily quota resets (documented).
- It is stored in the host's default cache store under key `youtube_quota_blocked_until`, with the end time as value and expiry.
- `Cable8mm\Youtube\Sync\QuotaBlock` has `isActive(): bool`, `until(): ?Carbon`, `start(): Carbon`, and `clear(): void`.
- Without a bound cache store a Quota Block is never active.

## 20. Events

All events are in namespace `Cable8mm\Youtube\Events`.

| Event | Fired | Public properties |
| --- | --- | --- |
| `ChannelSynced` | §18.2 step 8 | `YoutubeChannel $channel`, `int $created`, `int $updated`, `int $unavailable` (Video Records newly marked) |
| `ChannelSyncFailed` | §19.2 step 4 | `YoutubeChannel $channel`, `string $reason`, `Throwable $exception` |
| `QuotaExceeded` | §19.2 step 3 | `Carbon $until` |

## 21. Sync acceptance criteria

- Importing a CSV with rows `UCk1SpWNzOs4MYmr0uICEntg`, `@Google`, `https://www.youtube.com/user/Google`, `https://www.youtube.com/c/Google` gives `created`, `created`, `updated`, `failed: unsupported input`, and creates two Channel Records.
- Importing the same file again creates no Channel Record.
- `youtube:sync --channel=UCk1SpWNzOs4MYmr0uICEntg` followed by running the queued job creates Video Records for the channel's newest uploads with `title`, `published_at`, and `view_count` filled, and sets `sync_status` to `ok`.
- A second Sync creates no duplicate Video Records.
- A Sync with an invalid API Key creates a Sync Error Record with reason `invalid_key`, sets `sync_status` to `failed`, and fires `ChannelSyncFailed`.
- A Sync failing with `quota_exceeded` starts a Quota Block; while it is active, `youtube:sync` dispatches no job and a queued job sends no request.
