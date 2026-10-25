# PRODUCT_SPEC: cable8mm/youtube

Reference sections as `PRODUCT_SPEC.md §N`. Terms in §2 are used with exactly those meanings.

## 1. Purpose

- The product is a Laravel package named `cable8mm/youtube`.
- The product is a read-only client for the YouTube Data API v3. It authenticates with an API Key only. It has no OAuth.
- The product provides URL parsing for YouTube video and channel URLs.
- The product provides a validation rule that checks a URL points to an existing YouTube video.

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

## 3. Goals

- The Developer configures the API Key once, through `config/youtube.php` and the `YOUTUBE_API_KEY` environment variable.
- The Developer calls one method per YouTube Data API v3 read operation and receives Resource Objects.
- The Developer reduces API quota use with optional response caching (§10).
- The Developer validates submitted video URLs (§11).

## 4. Installation and configuration

- `composer require cable8mm/youtube` installs the product.
- Laravel package discovery registers `Cable8mm\Youtube\YoutubeServiceProvider` and the facade alias `Youtube`.
- `php artisan vendor:publish --provider="Cable8mm\Youtube\YoutubeServiceProvider"` publishes `config/youtube.php`.
- The setting `youtube.key` reads `YOUTUBE_API_KEY`. Its default is the string `YOUR_API_KEY`.
- `youtube.key` is the only setting. Caching and the Referer header are not settings; they are set on a Client (§5).

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

- No OAuth. No write operations (insert, update, delete, upload).
- No mapping of Resource Objects to typed objects, models, or storage.
- No automatic fetching of more than one page per call.
- No quota tracking, retries, or rate limiting.

## 15. Acceptance criteria

- With a valid API Key, every operation in §7.1 returns its listed result type.
- `getVideoInfo('rie-hPVJ7Sw')` returns a Resource Object with `id` and `snippet`; a nonexistent Video ID returns `null`.
- `parseVidFromURL` returns `rie-hPVJ7Sw` for `https://www.youtube.com/watch?v=rie-hPVJ7Sw`, `https://youtu.be/rie-hPVJ7Sw`, and `https://www.youtube.com/embed/rie-hPVJ7Sw`.
- With caching on, two identical calls send one request; a response matching §12 row 6 or 7 is never served from the cache.
- An invalid API Key raises §12 row 7 with code `400`.
- The validation rule produces each outcome in §11.
