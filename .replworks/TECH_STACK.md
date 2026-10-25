# TECH_STACK

Reference sections as `TECH_STACK.md §N`. Terms such as Client, Shared Client, and Transport are defined in PRODUCT_SPEC.md §2.

## 1. Languages / runtimes / frameworks / libraries

- Language: PHP, version constraint `^8.3`.
- Framework: Laravel, version constraint `^12.0|^13.0`, through `illuminate/support`.
- Package manager: composer.
- Runtime dependency `illuminate/support` `^12.0|^13.0`. Reason: service provider base class, facade base class, cache and validation contracts.
- Runtime dependencies `illuminate/console`, `illuminate/database`, `illuminate/queue`, `illuminate/events` `^12.0|^13.0`. Reason: Artisan commands, Eloquent models and migrations, queued jobs, events (PRODUCT_SPEC.md §16-§20).
- Runtime dependency `maatwebsite/excel` `^4.0`. Reason: reading CSV and Excel files for channel import (PRODUCT_SPEC.md §17). Its versions `4.*` require PHP `^8.3` and `illuminate/support` `^12.0 || ^13.0`.
- Runtime dependency `nesbot/carbon` `^3.0`. Reason: parsing and shifting the `getChannelVideos` time bound.
- Runtime extension `ext-curl`. Reason: HTTP transport to the YouTube Data API.
- Development dependency `orchestra/testbench` `^10.0|^11.0`. Reason: boots a Laravel application for package tests. Testbench `10.*` pairs with Laravel `12.*`; Testbench `11.*` pairs with Laravel `13.*`.
- Development dependency `phpunit/phpunit` `^11.5.50|^12.5.8`. Reason: test runner. The lower bounds are the minimums Testbench `11.*` accepts; Testbench `10.*` resolves to PHPUnit `11.*`.
- Development dependency `mockery/mockery` `^1.6`. Reason: test doubles in `tests/Cache/YoutubeCacheTest.php`.
- Development dependency `laravel/pint` `^1.0`. Reason: code formatter.
- Development dependency `vlucas/phpdotenv` `^5.0`. Reason: loads `.env` for E2E tests.
- Module system: PSR-4 autoload, namespace `Cable8mm\Youtube\` mapped to `src/`.
- Test autoload: PSR-4 autoload-dev, namespace `Cable8mm\Youtube\Tests\` mapped to `tests/`.

## 2. Framework- or tool-mandated paths and file names

- `composer.json` MUST exist at the repository root.
- `composer.lock` MUST NOT be committed.
- `src/config/youtube.php` MUST be the package config file. It is published as `config/youtube.php`.
- `src/` MUST hold package source.
- `tests/` MUST hold tests.
- `phpunit.xml.dist` MUST be the PHPUnit config file name.
- `.env.example` MUST list every environment variable the tests read.
- `.github/workflows/` MUST hold GitHub Actions workflow files.
- `LICENSE` MUST exist at the repository root.
- `CHANGELOG.md` MUST exist at the repository root.
- The service provider MUST be registered under `extra.laravel.providers` in `composer.json`.
- The facade alias `Youtube` MUST be registered under `extra.laravel.aliases` in `composer.json`.
- The service provider class MUST be `Cable8mm\Youtube\YoutubeServiceProvider`.
- The facade class MUST be `Cable8mm\Youtube\Facades\Youtube`.
- `database/migrations/` MUST hold the package migrations. They are published with `publishesMigrations()` under tag `youtube-migrations` and are not loaded with `loadMigrationsFrom()`.
- Class locations, by namespace under `Cable8mm\Youtube\` and folder under `src/`:

| Kind | Namespace / folder |
| --- | --- |
| Eloquent models | `Models` |
| Model traits | `Concerns` |
| Artisan commands | `Console` |
| Excel import objects | `Imports` |
| Queued jobs | `Jobs` |
| Events | `Events` |
| Failure classifier, Quota Block, channel input resolver | `Sync` |

- Command signatures MUST be `youtube:import-channels` and `youtube:sync`.
- `tests/fixtures/` MUST hold the CSV and XLSX files used by import tests.

## 3. Coding conventions specific to the chosen stack

- PHP code MUST be formatted with Laravel Pint, preset `laravel`. There is no `pint.json`.
- Files MUST follow `.editorconfig`: UTF-8, LF, 4-space indent, final newline. Markdown and YAML use 2-space indent.
- `declare(strict_types=1)` is not used in any file.
- Classes MUST follow PSR-4 file and namespace mapping.
- Public methods MUST have PHPDoc with `@return` when they return a value and `@throws` for each exception they raise.

## 4. Testing rules

- Tests MUST use PHPUnit.
- Tests that need a Laravel application MUST extend `Cable8mm\Youtube\Tests\TestCase`, which extends `Orchestra\Testbench\TestCase`.
- Test files MUST be named `*Test.php`.
- An E2E test is a test that sends a request to the YouTube Data API. "Live test" in TASKS.md means an E2E test.
- `phpunit.xml.dist` MUST define two test suites: `unit` (every test under `tests/` except `tests/E2E/`) and `e2e` (`tests/E2E/`).
- Every E2E test MUST be in `tests/E2E/` and extend `Cable8mm\Youtube\Tests\E2E\TestCase`.
- A test outside `tests/E2E/` MUST NOT send a request to the YouTube Data API. It uses a placeholder API Key such as `test_key` and never reads `YOUTUBE_API_KEY`.
- `Cable8mm\Youtube\Tests\E2E\TestCase` fails the test (it does not skip it) when `YOUTUBE_API_KEY` is unset or equals the `.env.example` placeholder. Otherwise it sets `youtube.key` to that key and rebinds the Shared Client. `apiKey()` returns the key for tests that build their own Client.
- Tests MUST read `YOUTUBE_API_KEY` and `YOUTUBE_CHANNEL_ID` only through `Cable8mm\Youtube\Tests\Env::get()`.
- Offline tests of API operations MUST replace the Transport with `Cable8mm\Youtube\Tests\Resources\FakeApiClient`, which records requests and returns queued bodies.
- Every body queued in `FakeApiClient` MUST follow a recorded live response or the YouTube Data API reference (AGENTS.md MOCK_RULES).
- Code coverage MUST NOT be measured or required.
- Tests that touch records MUST use SQLite in memory through Testbench, with the package migrations run by the test case (`defineDatabaseMigrations`) and `RefreshDatabase`.
- Offline sync and import tests MUST inject a Client built on `FakeApiClient` into the container in place of the Shared Client.
- Tests MAY use `Queue::fake()`, `Event::fake()`, and `Cache` with the `array` store. They MUST NOT fake the database.
- Live sync and import tests MUST run the job synchronously (`dispatchSync` or the `sync` queue connection).

## 5. Verification commands

| Command | Passes when |
| --- | --- |
| `composer test` | The `unit` suite exits 0. It sends no request to the YouTube Data API and needs no `.env`. |
| `composer test:e2e` | The `e2e` suite exits 0. Requires a valid `YOUTUBE_API_KEY` in `.env`. |
| `composer inspect` | `pint --test` reports no style changes. |
| `composer lint` | Applies Pint fixes. Use before `composer inspect`. |

- `composer test` runs `./vendor/bin/phpunit --testsuite unit`. `composer test:e2e` runs `./vendor/bin/phpunit --testsuite e2e`.
- Before running `composer test:e2e` in a fresh checkout, copy `.env.example` to `.env` and set `YOUTUBE_API_KEY`.

## 6. Configuration rules

- Package settings MUST be defined in `src/config/youtube.php`.
- The environment variable for the API Key MUST be `YOUTUBE_API_KEY`.
- `env()` MUST be called only inside `src/config/youtube.php`.
- Only `Cable8mm\Youtube\YoutubeServiceProvider` and the two Artisan commands read Laravel config. The commands pass `youtube.sync.*` values to jobs through job constructor arguments. Every other class receives the API Key through a constructor.
- Jobs and the import object obtain the Shared Client from the container (`app(Youtube::class)`), so tests can replace it.
- The Quota Block MUST use the host's default cache store, resolved the way the Transport resolves it (`app('cache')->store()` when `app()->bound('cache')`), with key `youtube_quota_blocked_until`.
- Sync jobs MUST implement `ShouldQueue`, set `$tries = 1`, and catch every `Throwable` inside `handle()`.

## 7. Deployment rules

- Distribution target MUST be Packagist.
- Package name MUST be `cable8mm/youtube`.
- Releases MUST be GitHub releases with semver tags in the format `vMAJOR.MINOR.PATCH`, for example `v6.0.0`. Release `v6.0.0` is the first that requires PHP `^8.3` and Laravel `^12.0|^13.0`.
- Publishing a release runs the `update changelog` workflow, which updates `CHANGELOG.md`.
- Publishing a release runs the `deploy-to-github-pages` workflow, which publishes API docs generated by Doctum (`composer apidoc`).
- CI MUST be GitHub Actions.
- The `run-tests` workflow runs on every push and on manual dispatch. Its matrix is PHP `8.3`, `8.4`, `8.5` × Laravel `12.*` (Testbench `10.*`), `13.*` (Testbench `11.*`): six jobs.
- Each `run-tests` job runs, in order: `composer validate`; `composer require "illuminate/support:<laravel>" --no-update`; `composer require --dev "orchestra/testbench:<testbench>" --no-update`; `composer update --prefer-stable --prefer-dist`; `composer test`.
- CI MUST NOT run the `e2e` suite. CI has no API Key.
- The `code-style` workflow runs Pint on every push and commits any fixes with the message `PHP Linting (Pint)`.
- License MUST be MIT.

## 8. Security rules tied to the chosen stack

- The API Key MUST NOT be committed. `.env` MUST stay in `.gitignore`.
- `.env.example` MUST contain only placeholder values.
- The API Key MUST NOT be written to logs, exception messages, or test output. It appears only in request URLs and, hashed, in cache keys.

## 9. Non-goals

- Static analysis tools, including PHPStan and Larastan, are not used.
- Code coverage measurement is not used.
- No HTTP client library other than `ext-curl` is used.
