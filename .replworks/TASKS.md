# TASKS

Execution checklist in dependency order. Select and run tasks as AGENTS.md TASK_SELECTION, TASK_EXECUTION, and AUTO_RUN define.

Conventions:

- `PS §N`, `TS §N`, and `AR §N` mean PRODUCT_SPEC.md, TECH_STACK.md, and ARCHITECTURE.md section N. `C1`-`C10`, `I1`-`I7`, and `F1`-`F6` are ARCHITECTURE.md IDs.
- "Unit tests passing" means the task's offline tests pass under `composer test` (TS §5).
- "Live tests passing" means the task's live tests pass under `YOUTUBE_ENABLED=true composer test` (TS §5).
- A task with "External boundary: yes" needs live tests (AGENTS.md TASK_EXECUTION step 8).
- Example values below were observed against the live API: video `rie-hPVJ7Sw`; playlist `PL590L5WQmH8fJ54F369BLDSqIwcs-TCfs`; channel `UCk1SpWNzOs4MYmr0uICEntg` (XDA); `@Google` and `/user/Google` resolve to channel `UCK8sQmJBp8GCxrOtXWBpyEA`.

Baseline: T-001 to T-016 describe the code at commit `0e6721a` (release `v5.1.1`).
- `[x]`: implemented, and every acceptance criterion is covered by a passing test.
- `[ ]`: implemented, but some acceptance criteria have no test. The work is to add those tests. Change product code only when a new test fails, and then follow AGENTS.md PRODUCT_SPEC_CHANGES.

Out of scope for every task until a human decides: the `[UNVERIFIED]` sections PS §8.1, PS §9.1, PS §11.1, TS §1.1, and TS §7.1.

- [x] T-001 Project foundation
  - Satisfies: TS §1, TS §2, TS §3, TS §7 (excluding TS §1.1 and TS §7.1)
  - External boundary: no
  - Acceptance criteria:
    - `composer.json` declares the constraints, autoload mappings, provider, and alias listed in TS §1 and TS §2.
    - The `run-tests` workflow passes on PHP `8.2`, `8.3`, `8.4`, and `8.5`.
    - `composer inspect` passes.

- [x] T-002 Developer configures the API Key and resolves the Shared Client
  - Satisfies: PS §4, PS §5 (container bindings)
  - External boundary: no
  - Acceptance criteria:
    - With no manual registration, `Config::get('youtube.key')` returns the configured value and `class_exists('Youtube')` is true.
    - `Youtube::getFacadeRoot()` is an instance of `Cable8mm\Youtube\Youtube`.
    - `app()->bound('youtube')` and `app()->bound(Cable8mm\Youtube\Youtube::class)` are true (C8).
    - With `youtube.key` set to `test_api_key`, `app(Cable8mm\Youtube\Youtube::class)->getApiKey()` returns `test_api_key`.
    - Unit tests passing.

- [x] T-003 Developer builds a Client and changes its settings fluently
  - Satisfies: PS §5 (constructor validation, fluent setters)
  - External boundary: no
  - Acceptance criteria:
    - `new Youtube('')` throws PS §12 row 1.
    - `(new Youtube('old_key'))->setApiKey('new_key')->getApiKey()` returns `new_key`.
    - `useHttpHost(true)` makes `getClient()->isUsingHttpHost()` true.
    - `cache(true)` makes `getClient()->isCacheEnabled()` true.
    - `setCacheTtl(1800)` makes `getClient()->getCacheTtl()` return `1800`.
    - `(new Youtube('test_key'))->useHttpHost(true)->setApiKey('another_key')->cache()->setCacheTtl(1800)->getApiKey()` returns `another_key`.
    - Unit tests passing.

- [x] T-004 Developer receives YoutubeApiException for error and malformed bodies
  - Satisfies: PS §12 rows 6 and 7; PS §6 (Page Info null values)
  - External boundary: no (C3 and C10 logic; the live error check is T-016)
  - Acceptance criteria:
    - Body `{"error":{"code":403,"message":"Forbidden","errors":[{"reason":"quotaExceeded"}]}}` raises `YoutubeApiException` with message `Error 403 Forbidden : quotaExceeded`.
    - `YoutubeApiException::fromApiError()` on that error object gives code `403`.
    - Bodies `''`, `<html>502 Bad Gateway</html>`, `{"items":[`, and `"ok"` each raise `YoutubeApiException` with a message starting `Invalid response from Youtube API` (F5).
    - `new YoutubeApiException('wrapped', 500, $previous)->getPrevious()` returns `$previous`.
    - Body `{"items":[]}` decoded as a Paged Result has `results` `false` and `info.kind` and `info.etag` `null`.
    - Unit tests passing.

- [x] T-005 Developer reduces API calls with response caching
  - Satisfies: PS §10; AR §4
  - External boundary: no
  - Acceptance criteria:
    - With caching on, two identical calls return the first body and send one request.
    - With caching on, an error body and an empty body are not stored: two identical calls send two requests.
    - `YoutubeCache` `remember`, `get`, `put`, `has`, `forget`, `clear`, and `setTtl` delegate to the repository with the wrapper's TTL.
    - `generateKey()` starts with `youtube_api_`, differs for different URLs or parameters, and is equal for the same parameters in a different order.
    - Unit tests passing.

- [x] T-006 Developer reads videos by ID and popular videos by region
  - Satisfies: PS §7.1 (`getVideoInfo`, `getPopularVideos`); PS §6
  - External boundary: yes
  - Acceptance criteria:
    - `getVideoInfo('rie-hPVJ7Sw')` returns a Resource Object with `id` and `snippet`.
    - `getVideoInfo('invalid_video_id_12345')` returns `null`.
    - `getVideoInfo('abc', ['id'])` sends exactly `id=abc`, `part=id`, `key`.
    - `getVideoInfo(['a', 'b'])` sends `id=a,b` and returns an array.
    - `getPopularVideos('US', 5)` returns a non-empty array.
    - Unit tests passing.
    - Live tests passing.

- [x] T-007 Developer searches videos and pages through a channel's videos
  - Satisfies: PS §7.1 (`searchVideos`, `searchChannelVideos`, `listChannelVideos` offline, `getChannelVideos` with `$isFuture` false, `searchAdvanced`, `paginateResults`); PS §7.2; PS §7.3; PS §6
  - External boundary: yes
  - Acceptance criteria:
    - `searchVideos('Laravel', 5)` returns a non-empty array.
    - `searchChannelVideos('Android', 'UCk1SpWNzOs4MYmr0uICEntg', 5)` returns a non-empty array.
    - `getChannelVideos($channelId, 1, null)` returns a Paged Result whose `info` has `totalResults` and `nextPageToken`.
    - Calling `getChannelVideos($channelId, 1, <publishedAt of the first result>)` returns a different video.
    - `paginateResults(['q' => 'Laravel', 'type' => 'video', 'part' => 'id,snippet', 'maxResults' => 10])` returns a Paged Result.
    - `searchAdvanced([])` throws PS §12 row 2.
    - Offline: a search body with no items returns `false`.
    - Offline: `listChannelVideos('UC1', 1, 'date', ['id'], true)` sends `order=date` and returns Page Info with `nextPageToken` `NEXT`, `prevPageToken` `null`, and `totalResults` `10` for the matching body.
    - Unit tests passing.
    - Live tests passing.

- [x] T-008 Developer reads channels by ID and by legacy username
  - Satisfies: PS §7.1 (`getChannelById`, `getChannelByName`)
  - External boundary: yes
  - Acceptance criteria:
    - `getChannelById('UCk1SpWNzOs4MYmr0uICEntg')` returns a Resource Object with `id` and `snippet`.
    - `getChannelByName('Google')` returns a Resource Object with `id`.
    - Live tests passing.

- [x] T-009 Developer reads playlists and playlist items
  - Satisfies: PS §7.1 (`getPlaylistById`, `getPlaylistItemsByPlaylistId`; `getPlaylistsByChannelId` offline); PS §6
  - External boundary: yes
  - Acceptance criteria:
    - `getPlaylistById('PL590L5WQmH8fJ54F369BLDSqIwcs-TCfs')` returns a Resource Object with `id` and `snippet`.
    - `getPlaylistItemsByPlaylistId('PL590L5WQmH8fJ54F369BLDSqIwcs-TCfs')` returns an array with keys `results` and `info`.
    - Offline: `getPlaylistsByChannelId('UC1')` for a body without tokens or `pageInfo` returns `info` `['totalResults' => 0, 'nextPageToken' => false, 'prevPageToken' => false]`.
    - Unit tests passing.
    - Live tests passing.

- [x] T-010 Developer lists video categories by region
  - Satisfies: PS §7.1 (`getCategories`)
  - External boundary: yes
  - Acceptance criteria:
    - `getCategories('US')` returns a non-empty array.
    - Live tests passing.

- [x] T-011 Developer extracts a Video ID from a YouTube URL
  - Satisfies: PS §8 (excluding PS §8.1)
  - External boundary: no
  - Acceptance criteria:
    - `https://www.youtube.com/watch?v=rie-hPVJ7Sw`, `https://youtu.be/rie-hPVJ7Sw`, and `https://www.youtube.com/embed/rie-hPVJ7Sw` return `rie-hPVJ7Sw`.
    - `https://www.youtube.com/watch?t=12&v=rie-hPVJ7Sw&list=PL1` and `https://www.youtube.com/watch?v=rie%2DhPVJ7Sw` return `rie-hPVJ7Sw`.
    - These throw the Video URL Error: `https://example.com`, `https://www.youtube.com/`, `https://www.youtube.com`, `https://www.youtube.com/watch?v=`, `https://www.youtube.com/watch?v[]=abc`, `https://www.youtube.com/embed/`, `https://youtu.be`, `https://youtu.be/`.
    - `UrlParser::query('https://www.youtube.com/watch')` returns `[]`; `UrlParser::path('https://youtu.be')` returns `''`.
    - Unit tests passing.

- [x] T-012 Developer resolves a channel from its URL
  - Satisfies: PS §9 (excluding PS §9.1); AR §3.2
  - External boundary: no (C5 and C4 dispatch; the live check is T-016)
  - Acceptance criteria:
    - `UrlParser::channel()` returns `['type' => 'id', 'value' => 'UC1']` for `/channel/UC1`, `['type' => 'username', 'value' => 'google']` for `/user/google`, and `['type' => 'search', 'value' => 'name']` for `/c/name` and `/name`.
    - `/feed/trending` and `https://www.youtube.com` throw the Channel URL Error.
    - Offline: `getChannelFromURL('https://www.youtube.com/@laravelphp')` sends a search with `q=laravelphp`, then a channels request with the first hit's `snippet.channelId` as `id`.
    - Unit tests passing.

- [x] T-013 Developer validates that a URL points to an existing YouTube video
  - Satisfies: PS §11 (excluding PS §11.1)
  - External boundary: yes
  - Acceptance criteria:
    - Live: `https://www.youtube.com/watch?v=rie-hPVJ7Sw`, `https://youtu.be/rie-hPVJ7Sw`, `https://www.youtube.com/embed/rie-hPVJ7Sw`, and `https://www.youtube.com/watch?v=rie-hPVJ7Sw&t=123` pass.
    - Live: `Validator::make(['video_url' => 'https://www.youtube.com/watch?v=rie-hPVJ7Sw'], ['video_url' => ['required', new ValidYoutubeVideo]])` passes with the Shared Client.
    - Offline: with an injected Client whose lookup returns `null`, the rule fails with exactly `The :attribute does not exist on Youtube.`
    - These fail: `https://example.com/watch?v=rie-hPVJ7Sw`, `https://www.youtube.com/watch`, `''`, `not a youtube url`, `https://vimeo.com/123456789`, `https://www.youtube.com/channel/UCk1SpWNzOs4MYmr0uICEntg`, `https://www.youtube.com/user/Google`, `https://www.youtube.com/feed/trending`.
    - Unit tests passing.
    - Live tests passing.

- [x] T-014 Developer runs live tests from `.env`
  - Satisfies: TS §4; TS §5
  - External boundary: yes
  - Acceptance criteria:
    - With `YOUTUBE_ENABLED=true` and a valid `YOUTUBE_API_KEY` in `.env`, `composer test` skips no test.
    - With `YOUTUBE_ENABLED=false`, every live test is skipped and `composer test` exits 0.
    - `Env::get('YOUTUBE_CHANNEL_ID')` returns the value set by `phpunit.xml.dist` in every test class.
    - Live tests that extend `Cable8mm\Youtube\Tests\TestCase` call the API with `YOUTUBE_API_KEY`, not `test_api_key`.
    - Live tests passing.

- [ ] T-015 Tests cover the Shared Client identity, constructor settings, and low-level helpers
  - Satisfies: PS §5 (Shared Client identity, `$config` keys); PS §13
  - External boundary: no
  - Acceptance criteria:
    - `app('youtube')`, `app(Cable8mm\Youtube\Youtube::class)`, and `Youtube::getFacadeRoot()` are the same object. After `Youtube::setApiKey('changed')`, `app('youtube')->getApiKey()` returns `changed`.
    - `new Youtube('k', ['use-http-host' => true, 'cache_enabled' => true, 'cache_ttl' => 60])` gives `isUsingHttpHost()` true, `isCacheEnabled()` true, and `getCacheTtl()` `60` on `getClient()`.
    - `getApi('videos.list')` returns `https://www.googleapis.com/youtube/v3/videos`.
    - For body `{"kind":"k","etag":"e","items":[{"id":"a"},{"id":"b"}]}`: `decodeSingle` returns the object with `id` `a`, `decodeMultiple` returns both objects, and `decodeList` returns both objects.
    - For body `{"kind":"k","etag":"e","items":[]}`: `decodeSingle` and `decodeMultiple` return `null`, and `decodeList` returns `false`.
    - Through `FakeApiClient`, `api_get($url, ['id' => 'a'])` returns the queued body and sends `key` with the Client's API Key.
    - `_parse_url_path($u)` equals `UrlParser::path($u)` and `_parse_url_query($u)` equals `UrlParser::query($u)` for `https://www.youtube.com/watch?v=abc`.
    - Unit tests passing.

- [ ] T-016 Live tests cover the remaining operations and the live error path
  - Satisfies: PS §7.1 (operations without a live test); PS §7.3 (`$isFuture` true); PS §9; PS §12 row 7; PS §15
  - External boundary: yes
  - Acceptance criteria:
    - `getLocalizedVideoInfo('rie-hPVJ7Sw', 'ko')` returns a Resource Object with `id` and `snippet`.
    - `search('Laravel', 5)` returns a non-empty array.
    - `listChannelVideos('UCk1SpWNzOs4MYmr0uICEntg', 5)` returns a non-empty array.
    - For `$t` = the `publishedAt` of the second-newest video of `UCk1SpWNzOs4MYmr0uICEntg`, `getChannelVideos('UCk1SpWNzOs4MYmr0uICEntg', 5, $t, true)` returns only videos whose `publishedAt` is later than `$t`.
    - `searchChannelByName('Google')` returns a Resource Object with `id`.
    - `getChannelFromURL()` returns channel `UCK8sQmJBp8GCxrOtXWBpyEA` for `https://www.youtube.com/@Google` and for `https://www.youtube.com/user/Google`.
    - `getChannelFromURL('https://www.youtube.com/channel/UCk1SpWNzOs4MYmr0uICEntg')` returns channel `UCk1SpWNzOs4MYmr0uICEntg`.
    - `getPlaylistsByChannelId('UCk1SpWNzOs4MYmr0uICEntg')` returns a Normalized Paged Result with a non-empty `results`.
    - `getCommentThreadsByVideoId('rie-hPVJ7Sw', 2)` returns an array of at most 2 items.
    - `getActivitiesByChannelId('UCk1SpWNzOs4MYmr0uICEntg')` returns an array of at most 5 items.
    - `(new Youtube('invalid_key'))->getVideoInfo('rie-hPVJ7Sw')` throws `YoutubeApiException` with code `400` and a message starting `Error 400` and ending ` : badRequest`.
    - Live tests passing.
