# Release Notes

## v6.5.1 - 2026-11-07

### What's Changed

* fix: classify short videos without shortsAspectRatio by duration by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/52

**Full Changelog**: https://github.com/cable8mm/Youtube/compare/v6.5.0...v6.5.1

## v6.5.0 - 2026-11-07

### What's Changed

* feat: record each video's kind during sync by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/51

**Full Changelog**: https://github.com/cable8mm/Youtube/compare/v6.4.0...v6.5.0

## v6.4.0 - 2026-11-07

### What's Changed

* feat: classify a video as shorts, live, upcoming, or regular by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/50

**Full Changelog**: https://github.com/cable8mm/Youtube/compare/v6.3.0...v6.4.0

## v6.3.0 - 2026-11-07

### What's Changed

* feat: parse shorts and live video URLs by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/47
* feat: return the video behind a video URL by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/48
* feat: resolve any channel input without a search fallback by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/49

**Full Changelog**: https://github.com/cable8mm/Youtube/compare/v6.2.0...v6.3.0

## v6.2.0 - 2026-11-07

### What's Changed

* feat: resolve a channel from a video URL by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/45
* docs: add subsession orchestration and probe paths by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/46

**Full Changelog**: https://github.com/cable8mm/Youtube/compare/v6.1.0...v6.2.0

## v6.1.0 - 2026-11-05

### What's Changed

* feat: format a Video Record duration by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/44

**Full Changelog**: https://github.com/cable8mm/Youtube/compare/v6.0.0...v6.1.0

## v6.0.0 - 2026-10-25

### What's Changed

* docs: add replworks baseline documents by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/22
* test: cover shared client identity, constructor settings and helpers by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/23
* test: add live tests for remaining operations and the error path by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/24
* feat!: require PHP 8.3+ and Laravel 12+ by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/25
* docs: specify channel import, video sync and sync failures by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/26
* feat: add getChannelByHandle by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/27
* test: move live API tests into a separate e2e suite by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/28
* feat: add channel, video and sync error records with publishable migrations by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/29
* feat: classify sync failures into Failure Reasons by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/30
* feat: add the Quota Block by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/31
* feat: resolve import cells to channels by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/32
* feat: import channels from CSV or Excel by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/33
* feat: sync a channel's videos with a queued job by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/34
* feat: flag videos that are no longer available by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/35
* feat: add the youtube:sync command by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/36
* docs: document the Laravel channel sync features by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/37
* docs: refresh README features, test counts and package structure by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/38
* docs: decide the URL parsing and validation gaps as bugs by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/39
* fix: parse video URLs by host and path by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/40
* fix: resolve channel URLs by path segment and look handles up directly by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/41
* fix: make the video validation rule fail instead of throwing by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/42
* docs: rewrite README around Laravel usage by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/43

**Full Changelog**: https://github.com/cable8mm/Youtube/compare/v5.1.1...v6.0.0

## v5.1.1 - 2026-10-25

### What's Changed

* Refactor Youtube class into client, decoder and resources; fix 5 bugs by [@replworks-bot](https://github.com/replworks-bot) in https://github.com/cable8mm/Youtube/pull/21

### New Contributors

* [@replworks-bot](https://github.com/replworks-bot) made their first contribution in https://github.com/cable8mm/Youtube/pull/21

**Full Changelog**: https://github.com/cable8mm/Youtube/compare/v5.1.0...v5.1.1

## v5.1.0 - 2026-07-06

### What's Changed

* feat: improvements by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/20

**Full Changelog**: https://github.com/cable8mm/Youtube/compare/v5.0.1...v5.1.0

## v5.0.1 - 2025-12-02

### What's Changed

* Add php84 for testing by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/18
* Update `nesbot/carbon` to 3.x by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/19

**Full Changelog**: https://github.com/cable8mm/Youtube/compare/v5.0.0...v5.0.1

## v5.0.0 - 2025-12-01

### What's Changed

* Let me show a api link in the README by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/16
* Add laravel 12 required by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/17

**Full Changelog**: https://github.com/cable8mm/Youtube/compare/v4.2.0...v5.0.0

## v4.2.0 - 2024-03-09

### What's Changed

* Organizing some files by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/14
* Add deployment to github pages and fix class comment errors by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/15

**Full Changelog**: https://github.com/cable8mm/Youtube/compare/v4.1.0...v4.2.0

## v4.1.0 - 2024-02-22

### What's Changed

* Add pageToken parameter by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/13

**Full Changelog**: https://github.com/cable8mm/Youtube/compare/v4.0.0...v4.1.0

## v4.0.0 - 2024-02-22

### What's Changed

* Change getChannelVideos method to need pageinfo attribute by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/12

**Full Changelog**: https://github.com/cable8mm/Youtube/compare/v3.2.1...v4.0.0

## v3.2.1 - 2024-02-08

### What's Changed

* Add update changelog action by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/10
* Fix a bug not to retrive a infomation of videos in the future by [@cable8mm](https://github.com/cable8mm) in https://github.com/cable8mm/Youtube/pull/11

**Full Changelog**: https://github.com/cable8mm/Youtube/compare/v3.2.0...v3.2.1
