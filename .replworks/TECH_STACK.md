# TECH_STACK

Reference sections as `TECH_STACK.md §N`. Terms such as Client, Shared Client, and Transport are defined in PRODUCT_SPEC.md §2.

## 1. Languages / runtimes / frameworks / libraries

- Language: PHP, version constraint `^8.2`.
- Framework: Laravel, version constraint `^10.0|^11.0|^12.0|^13.0`, through `illuminate/support`.
- Package manager: composer.
- Runtime dependency `illuminate/support` `^10.0|^11.0|^12.0|^13.0`. Reason: service provider base class, facade base class, cache and validation contracts.
- Runtime dependency `nesbot/carbon` `^3.0`. Reason: parsing and shifting the `getChannelVideos` time bound.
- Runtime extension `ext-curl`. Reason: HTTP transport to the YouTube Data API.
- Development dependency `orchestra/testbench` `^8.0|^9.0|^10.0|^11.0`. Reason: boots a Laravel application for package tests.
- Development dependency `phpunit/phpunit` `^9.0|^10.0|^11.0`. Reason: test runner.
- Development dependency `laravel/pint` `^1.0`. Reason: code formatter.
- Development dependency `vlucas/phpdotenv` `^5.0`. Reason: loads `.env` for live tests.
- Module system: PSR-4 autoload, namespace `Cable8mm\Youtube\` mapped to `src/`.
- Test autoload: PSR-4 autoload-dev, namespace `Cable8mm\Youtube\Tests\` mapped to `tests/`.

### 1.1 Open gap [UNVERIFIED]

- Tests use PHPUnit attributes (`#[DataProvider]`). PHPUnit 9 ignores attributes, so the suite cannot pass on `phpunit/phpunit` `^9.0`, which the constraint allows.
- Tests use `mockery/mockery`, which is not declared in `composer.json`. It is installed only as a dependency of `orchestra/testbench`.
- Whether to narrow the PHPUnit constraint and declare Mockery is undecided.

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
- A live test is a test that calls the YouTube Data API. Every live test MUST be skipped unless `YOUTUBE_ENABLED` is `true`.
- Tests MUST read `YOUTUBE_API_KEY`, `YOUTUBE_CHANNEL_ID`, and `YOUTUBE_ENABLED` only through `Cable8mm\Youtube\Tests\Env::get()` and `Env::youtubeEnabled()`.
- A live test that extends `Cable8mm\Youtube\Tests\TestCase` MUST start with `$this->useLiveApiKey()`. It skips the test when live tests are off and otherwise rebinds the Shared Client with the real API Key.
- A live test that does not extend it MUST build its Client with `Env::get('YOUTUBE_API_KEY')`.
- Offline tests of API operations MUST replace the Transport with `Cable8mm\Youtube\Tests\Resources\FakeApiClient`, which records requests and returns queued bodies.
- Every body queued in `FakeApiClient` MUST follow a recorded live response or the YouTube Data API reference (AGENTS.md MOCK_RULES).
- Code coverage MUST NOT be measured or required.

## 5. Verification commands

| Command | Passes when |
| --- | --- |
| `composer test` | PHPUnit exits 0. Live tests are skipped unless `.env` sets `YOUTUBE_ENABLED=true`. |
| `YOUTUBE_ENABLED=true composer test` | PHPUnit exits 0 with live tests running. Requires a valid `YOUTUBE_API_KEY` in `.env`. |
| `composer inspect` | `pint --test` reports no style changes. |
| `composer lint` | Applies Pint fixes. Use before `composer inspect`. |

- `composer test` runs `./vendor/bin/phpunit tests`.
- Before running tests in a fresh checkout, copy `.env.example` to `.env`.

## 6. Configuration rules

- Package settings MUST be defined in `src/config/youtube.php`.
- The environment variable for the API Key MUST be `YOUTUBE_API_KEY`.
- `env()` MUST be called only inside `src/config/youtube.php`.
- Only `Cable8mm\Youtube\YoutubeServiceProvider` reads Laravel config. Every other class receives the API Key through a constructor.

## 7. Deployment rules

- Distribution target MUST be Packagist.
- Package name MUST be `cable8mm/youtube`.
- Releases MUST be GitHub releases with semver tags in the format `vMAJOR.MINOR.PATCH`, for example `v5.1.1`.
- Publishing a release runs the `update changelog` workflow, which updates `CHANGELOG.md`.
- Publishing a release runs the `deploy-to-github-pages` workflow, which publishes API docs generated by Doctum (`composer apidoc`).
- CI MUST be GitHub Actions.
- The `run-tests` workflow runs on every push and on manual dispatch, on PHP `8.2`, `8.3`, `8.4`, and `8.5`. Its steps are `composer validate`, `composer install --prefer-dist --no-progress --no-suggest`, copy `.env.example` to `.env`, and `composer test`.
- The `code-style` workflow runs Pint on every push and commits any fixes with the message `PHP Linting (Pint)`.
- License MUST be MIT.

### 7.1 Open gap [UNVERIFIED]

- The CI matrix varies PHP only and installs the newest allowed dependencies. Laravel `10.*` and `11.*` (Testbench `8.*` and `9.*`) are never installed in CI, though `composer.json` allows them.
- Whether to add a Laravel axis to the matrix or narrow the constraints is undecided.

## 8. Security rules tied to the chosen stack

- The API Key MUST NOT be committed. `.env` MUST stay in `.gitignore`.
- `.env.example` MUST contain only placeholder values.
- The API Key MUST NOT be written to logs, exception messages, or test output. It appears only in request URLs and, hashed, in cache keys.

## 9. Non-goals

- Static analysis tools, including PHPStan and Larastan, are not used.
- Code coverage measurement is not used.
- No HTTP client library other than `ext-curl` is used.
