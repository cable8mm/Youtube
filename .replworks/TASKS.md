# TASKS

Execution checklist in dependency order. Select and run tasks as AGENTS.md TASK_SELECTION, TASK_EXECUTION, and AUTO_RUN define.

Conventions:

- `PS §N`, `TS §N`, and `AR §N` mean PRODUCT_SPEC.md, TECH_STACK.md, and ARCHITECTURE.md section N. `C1`-`C18`, `I1`-`I12`, and `F1`-`F9` are ARCHITECTURE.md IDs.
- "Unit tests passing" means the task's offline tests pass under `composer test` (TS §5).
- "Live tests passing" means the task's E2E tests pass under `composer test:e2e` (TS §5). Before T-028 the command was `YOUTUBE_ENABLED=true composer test`.
- A task with "External boundary: yes" needs live tests (AGENTS.md TASK_EXECUTION step 8).
- Example values below were observed against the live API: video `rie-hPVJ7Sw`; playlist `PL590L5WQmH8fJ54F369BLDSqIwcs-TCfs`; channel `UCk1SpWNzOs4MYmr0uICEntg` (XDA); `@Google` and `/user/Google` resolve to channel `UCK8sQmJBp8GCxrOtXWBpyEA`.

Baseline: T-001 to T-016 describe the code at commit `0e6721a` (release `v5.1.1`).
- `[x]`: implemented, and every acceptance criterion is covered by a passing test.
- `[ ]`: implemented, but some acceptance criteria have no test. The work is to add those tests. Change product code only when a new test fails, and then follow AGENTS.md PRODUCT_SPEC_CHANGES.

Out of scope for every task until a human decides: the `[UNVERIFIED]` sections PS §8.1, PS §9.1, and PS §11.1.

T-018 onward are new work for release `v6.0.0`: build the code, then test it.
- [x] T-001 Project foundation
  - Satisfies: TS §1, TS §2, TS §3, TS §7
  - External boundary: no
  - Acceptance criteria:
    - `composer.json` declares the constraints, autoload mappings, provider, and alias listed in TS §1 and TS §2.
    - The `run-tests` workflow passes on PHP `8.2`, `8.3`, `8.4`, and `8.5`. Superseded by T-017.
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
    - With `YOUTUBE_ENABLED=true` and a valid `YOUTUBE_API_KEY` in `.env`, `composer test` skips no test. Superseded by T-028.
    - With `YOUTUBE_ENABLED=false`, every live test is skipped and `composer test` exits 0. Superseded by T-028.
    - `Env::get('YOUTUBE_CHANNEL_ID')` returns the value set by `phpunit.xml.dist` in every test class.
    - Live tests that extend `Cable8mm\Youtube\Tests\TestCase` call the API with `YOUTUBE_API_KEY`, not `test_api_key`. Superseded by T-028.
    - Live tests passing.

- [x] T-015 Tests cover the Shared Client identity, constructor settings, and low-level helpers
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

- [x] T-016 Live tests cover the remaining operations and the live error path
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

- [x] T-017 Package requires PHP 8.3+ and Laravel 12+
  - Satisfies: TS §1; TS §7
  - External boundary: no
  - Acceptance criteria:
    - `composer.json` requires `php` `^8.3` and `illuminate/support` `^12.0|^13.0`.
    - `composer.json` requires-dev `orchestra/testbench` `^10.0|^11.0`, `phpunit/phpunit` `^11.5.50|^12.5.8`, and `mockery/mockery` `^1.6`.
    - `composer validate` exits 0.
    - The `run-tests` matrix has six jobs: PHP `8.3`, `8.4`, `8.5` × Laravel `12.*` with Testbench `10.*`, and × Laravel `13.*` with Testbench `11.*`.
    - Every `run-tests` job passes.
    - README requirements list PHP 8.3 or higher and Laravel 12.x or 13.x.

- [x] T-018 Developer looks up a channel by Handle
  - Satisfies: PS §7.1 (`getChannelByHandle`)
  - External boundary: yes
  - Acceptance criteria:
    - Through `FakeApiClient`, `getChannelByHandle('@Google')` sends `forHandle`=`@Google` and `part`=`id,snippet,contentDetails,statistics` to channels.
    - `$optionalParams` replaces generated values (PS §7.2).
    - `getChannelByHandle('@Google')` returns a Resource Object with `id` `UCK8sQmJBp8GCxrOtXWBpyEA`.
    - `getChannelByHandle('Google')` (without `@`) returns the same channel.
    - A Handle with no channel returns `null`.
    - Unit tests passing. Live tests passing.

- [x] T-019 Developer publishes migrations and uses the record models
  - Satisfies: PS §4 (migration publishing, settings); PS §16; TS §1 (new runtime dependencies); TS §2; C8; C9; C11
  - External boundary: no
  - Acceptance criteria:
    - `composer.json` requires `illuminate/console`, `illuminate/database`, `illuminate/queue`, `illuminate/events` `^12.0|^13.0` and `maatwebsite/excel` `^4.0`.
    - `vendor:publish --tag=youtube-migrations` copies the migrations for `youtube_channels`, `youtube_videos`, and `youtube_sync_errors` into the host's `database/migrations`.
    - After migrating, each table has exactly the columns, nullability, defaults, unique keys, and foreign-key delete rules of PS §16.1-§16.3.
    - `YoutubeChannel::videos()`, `syncErrors()`, `owner()`, `YoutubeVideo::channel()`, and `YoutubeSyncError::channel()` return the related records.
    - A test model using `HasYoutubeChannels` returns its Channel Records from `youtubeChannels()`.
    - Deleting a Channel Record deletes its Video Records and sets `youtube_channel_id` to `null` on its Sync Error Records.
    - `config('youtube.sync.queue')` is `null` and `config('youtube.sync.max_pages')` is `1` by default; `YOUTUBE_SYNC_QUEUE` sets the queue.
    - Unit tests passing.

- [ ] T-020 Sync failures are classified into Failure Reasons
  - Satisfies: PS §19.1; C16
  - External boundary: no
  - Acceptance criteria:
    - `FailureReason::fromThrowable()` returns, for a `YoutubeApiException` built from:
      - code `429`, reason `rateLimitExceeded`: `quota_exceeded`
      - code `403`, reason `quotaExceeded`: `quota_exceeded`
      - code `403`, reason `dailyLimitExceeded`: `quota_exceeded`
      - code `400`, message `API key not valid. Please pass a valid API key.`, reason `badRequest`: `invalid_key`
      - code `403`, reason `forbidden`: `forbidden`
      - code `404`, reason `playlistNotFound`: `channel_not_found`
      - message `Curl Error : timeout`: `transport`
      - message `Invalid response from Youtube API : Syntax error`: `transport`
    - Any other `Throwable` returns `unknown`.
    - Error bodies in tests follow the observed values in PS §12 and PS §19.1, or the YouTube Data API error reference.
    - Unit tests passing.

- [ ] T-021 Quota Block starts, expires, and clears
  - Satisfies: PS §19.3; C17
  - External boundary: no
  - Acceptance criteria:
    - With the test time `2026-10-25 15:00 America/Los_Angeles`, `QuotaBlock::start()` returns `2026-10-26 00:00 America/Los_Angeles` and `until()` returns the same time.
    - `isActive()` is true after `start()`, false after `clear()`, and false once the test time passes `until()`.
    - With no bound `cache` service, `isActive()` is false and `start()` does not throw.
    - The cache key is `youtube_quota_blocked_until`.
    - Unit tests passing.

- [ ] T-022 Import cells resolve to channels
  - Satisfies: PS §17.2; C12; I9
  - External boundary: yes
  - Acceptance criteria:
    - Offline, each input row of PS §17.2 sends the listed request with the listed argument, and sends no search request.
    - `https://www.youtube.com/c/Google`, `hello world`, and an empty cell send no request and give `unsupported input`.
    - Live, `UCk1SpWNzOs4MYmr0uICEntg` resolves to that channel; `@Google`, `https://www.youtube.com/@Google`, and `https://www.youtube.com/user/Google` resolve to `UCK8sQmJBp8GCxrOtXWBpyEA`.
    - Live, a Channel ID with no channel gives `channel not found`.
    - Unit tests passing. Live tests passing.

- [ ] T-023 Administrator imports channels from CSV or Excel
  - Satisfies: PS §17.1; PS §17.3; PS §21 (first two criteria); C13; C8 (command registration); F7
  - External boundary: yes
  - Acceptance criteria:
    - `tests/fixtures/channels.csv` and `tests/fixtures/channels.xlsx` hold the four rows of PS §21. Importing either, live, gives `created`, `created`, `updated`, `failed: unsupported input` and two Channel Records with `channel_id`, `title`, and `uploads_playlist_id` set.
    - Importing the same file again gives `updated`, `updated`, `updated`, `failed: unsupported input` and no new Channel Record.
    - Re-import keeps `is_active`, `sync_status`, and Video Records of existing Channel Records.
    - `new YoutubeChannelsImport($owner)` sets the Owner on every created or updated Channel Record.
    - A file without a `channel` header imports nothing and reports `missing channel column`.
    - Offline, a `quota_exceeded` error on row 2 of 4 gives `failed: quota_exceeded` on row 2 and `skipped: quota exceeded` on rows 3-4.
    - `youtube:import-channels` prints the counts and failed rows of PS §17.3, exits `1` when a row failed, and exits `0` otherwise.
    - Unit tests passing. Live tests passing.

- [ ] T-024 Sync job stores a channel's videos and records failures
  - Satisfies: PS §18.2 (steps 1-6 and 8); PS §19.2; PS §20; C15; C18; I8; I10; I11; F8; F9
  - External boundary: yes
  - Acceptance criteria:
    - Live, `SyncChannelVideos` for the Channel Record of `UCk1SpWNzOs4MYmr0uICEntg` creates up to 50 Video Records with `video_id`, `title`, `published_at`, `duration`, `thumbnail_url`, and `view_count` set, and sets `sync_status` `ok` and `last_synced_at`.
    - Running it again creates no new Video Record and fires `ChannelSynced` with `created` `0`.
    - A Channel Record with `uploads_playlist_id` `null` gets it filled before the playlist is read.
    - Offline, with `$maxPages` `2` and three queued playlist pages, exactly two playlistItems requests are sent; with `null`, all three.
    - Offline, the check set includes known Video IDs, and videos.list requests carry at most 50 IDs each.
    - A missing `statistics.likeCount` stores `like_count` `null`.
    - Live, with an invalid API Key, one Sync Error Record with reason `invalid_key` and code `400` is created, `sync_status` becomes `failed`, `last_error_reason` becomes `invalid_key`, `ChannelSyncFailed` fires, and no exception leaves the job.
    - Offline, a `quota_exceeded` error starts a Quota Block and fires `QuotaExceeded`.
    - Offline, a channel lookup returning no item records `channel_not_found`.
    - During a Quota Block the job sends no request and changes no record.
    - Unit tests passing. Live tests passing.

- [ ] T-025 Sync flags videos that are no longer available
  - Satisfies: PS §18.2 (step 7); PS §18.3
  - External boundary: yes
  - Observed IDs for live tests (PS §18.3): private `3x_bMyRvJu0`, deleted `Flf-L9FjaV8`.
  - Acceptance criteria:
    - A Video Record not returned by videos.list gets `unavailable_at` set; an already-set value is kept.
    - A Video Record returned again gets `unavailable_at` `null`.
    - No Video Record is deleted.
    - `ChannelSynced` reports the number of newly flagged Video Records in `unavailable`.
    - Unit tests passing. Live tests passing.

- [ ] T-026 Scheduler runs youtube:sync
  - Satisfies: PS §18.1; PS §21 (last four criteria); C14; I12
  - External boundary: yes
  - Acceptance criteria:
    - With `Queue::fake()`, `youtube:sync` pushes one `SyncChannelVideos` per active Channel Record, on queue `youtube.sync.queue`, with `$maxPages` = `youtube.sync.max_pages`.
    - `--full` pushes jobs with `$maxPages` `null`.
    - `--channel=<id>` pushes a job for that Channel Record even when inactive; an unknown ID prints `unknown channel <id>` and pushes nothing for it.
    - During a Quota Block the command pushes nothing, prints `quota blocked until <time>`, and exits `0`.
    - Live, on the `sync` queue connection, `youtube:sync --channel=UCk1SpWNzOs4MYmr0uICEntg` stores Video Records and sets `sync_status` `ok`.
    - Unit tests passing. Live tests passing.

- [ ] T-027 README documents the Laravel sync features
  - Satisfies: PS §3 (administration screen built by the host); PS §4; PS §17; PS §18.1; PS §19; PS §20
  - External boundary: no
  - Acceptance criteria:
    - README lists the publish commands for config and migrations.
    - README shows `youtube:import-channels`, the `channel` column, and the accepted input forms.
    - README shows scheduling `youtube:sync` and running a queue worker.
    - README lists the Failure Reasons, the Quota Block, and the three events.
    - README shows one Filament resource and one Nova resource example reading `YoutubeChannel` and `YoutubeSyncError`.

- [x] T-028 E2E tests run separately from unit tests and never in CI
  - Satisfies: TS §4; TS §5; TS §7
  - External boundary: yes
  - Acceptance criteria:
    - Every test that sends a request to the YouTube Data API is in `tests/E2E/` and extends `Cable8mm\Youtube\Tests\E2E\TestCase`. No test outside `tests/E2E/` sends one.
    - `composer test` runs only the `unit` suite and exits 0 without `.env`.
    - `composer test:e2e` runs only the `e2e` suite. With a valid `YOUTUBE_API_KEY` in `.env` it skips no test.
    - With `YOUTUBE_API_KEY` unset or equal to the `.env.example` placeholder, every E2E test fails with a message naming `YOUTUBE_API_KEY`.
    - `YOUTUBE_ENABLED`, `Env::youtubeEnabled()`, and `TestCase::useLiveApiKey()` no longer exist; `.env.example` has no `YOUTUBE_ENABLED`.
    - The `run-tests` workflow runs `composer test` and does not copy `.env.example`.
    - README Testing section shows `composer test` and `composer test:e2e`.
    - Unit tests passing. Live tests passing.
