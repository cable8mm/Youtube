# AGENTS.md - AI Agent Guide for YouTube Laravel Package

## 📌 Project Overview

This is a Laravel package for YouTube Data API v3 (Non-OAuth). It provides a clean, fluent interface for interacting with YouTube API with comprehensive test coverage.

## 🎯 Key Information for AI Agents

### Project Structure

```text
src/
├── Youtube.php                    # Main class - all API methods
├── YoutubeServiceProvider.php     # Laravel service provider (singleton binding)
├── Facades/
│   └── Youtube.php                # Laravel Facade
├── Rules/
│   └── ValidYoutubeVideo.php      # Laravel 10+ ValidationRule (closure-based)
├── Cache/
│   └── YoutubeCache.php           # Caching wrapper for Laravel Cache
├── Exceptions/
│   └── YoutubeApiException.php    # Custom exception with factory method
└── config/
    └── youtube.php                # Configuration file

tests/
├── TestCase.php                   # Orchestra Testbench base class
├── YoutubeTest.php                # Main class tests (30 tests)
├── LaravelIntegrationTest.php     # Laravel integration tests (14 tests)
├── YoutubeDevEnvironmentTest.php  # Environment check test
├── Rules/
│   └── ValidYoutubeVideoTest.php  # Validation rule tests (13 tests)
└── Cache/
    └── YoutubeCacheTest.php       # Cache tests (18 tests)
```

### Critical Implementation Details

#### 1. **Service Binding**

- Uses **singleton** binding in `YoutubeServiceProvider`
- API key is set via constructor or `setApiKey()` method
- Configuration: `config('youtube.key')`

#### 2. **Validation Rule (Laravel 10+)**

- Uses `ValidationRule` interface (NOT the old `Rule` interface)
- Method signature: `validate(string $attribute, mixed $value, Closure $fail): void`
- Uses direct class instantiation (`new \Cable8mm\Youtube\Youtube()`) NOT Facade
- Facade doesn't have static methods like `parseVidFromURL()`

#### 3. **Caching System**

- Optional caching via Laravel Cache
- Enable: `$youtube->cache()` or constructor config
- TTL: `$youtube->setCacheTtl(seconds)` or constructor config
- Cache check: `function_exists('app') && app()->bound('cache')`

#### 4. **Error Handling**

- Custom exception: `YoutubeApiException`
- Factory method: `YoutubeApiException::fromApiError($errorObj)`
- All API errors throw `YoutubeApiException`

#### 5. **Type Safety**

- PHP 8.2+ required
- Constructor Property Promotion used
- Strict return types on all public methods
- Nullable types: `?string`, `?int`

### Testing Strategy

#### Test Framework

- **PHPUnit 11.5** with **Orchestra Testbench**
- Total: 73 tests, 100% pass rate
- Unit tests: No API key required
- Integration tests: Require `YOUTUBE_ENABLED=true` in .env

#### Running Tests

```bash
# All tests (unit tests only, API tests skipped)
composer test

# With API tests
YOUTUBE_ENABLED=true composer test

# Specific test
./vendor/bin/phpunit --filter test_name
```

#### Test Best Practices

- Use `markTestSkipped()` for tests requiring API key
- Mock external dependencies with Mockery
- Use reflection for testing private/protected properties
- Test both success and failure cases

### Code Style

#### PHP Standards

- PSR-12 compliant
- Laravel Pint for formatting
- Strict types enabled
- No `var` keyword, use `public/protected/private`

#### Naming Conventions

- Methods: camelCase
- Classes: PascalCase
- Constants: UPPER_SNAKE_CASE
- Private methods: camelCase with leading underscore if needed

#### Documentation

- PHPDoc blocks for all public methods
- Return types in PHPDoc
- Parameter types in PHPDoc
- @throws tags for exceptions

### Common Pitfalls to Avoid

1. **Facade Static Calls**
   - ❌ `Youtube::parseVidFromURL()` - FAILS
   - ✅ `\Cable8mm\Youtube\Youtube::parseVidFromURL()` - WORKS

2. **Validation Rule**
   - ❌ Old `Rule` interface with `passes()` method
   - ✅ New `ValidationRule` interface with `validate()` method

3. **Service Binding**
   - ❌ `bind()` - creates new instance each time
   - ✅ `singleton()` - reuses instance

4. **Config Access**
   - ❌ `config('youtube.key')` in src files (use dependency injection)
   - ✅ `\Config::get('youtube.key')` in Rules (Laravel helper)

### Dependencies

#### Required

- PHP: ^8.2
- illuminate/support: ^10.0|^11.0|^12.0|^13.0
- nesbot/carbon: ^3.0
- ext-curl: \*

#### Dev

- orchestra/testbench: ^8.0|^9.0|^10.0|^11.0
- phpunit/phpunit: ^9.0|^10.0|^11.0
- laravel/pint: ^1.0
- vlucas/phpdotenv: ^5.0

### Recent Changes

1. **Laravel 10+ ValidationRule**: Migrated from `Rule` to `ValidationRule` interface
2. **Type Safety**: Added strict typing throughout
3. **Caching**: Built-in response caching with configurable TTL
4. **Custom Exceptions**: Domain-specific `YoutubeApiException`
5. **Testbench Integration**: Full Laravel integration tests
6. **Singleton Binding**: Changed from `bind` to `singleton`

### When Working on This Project

1. **Always run tests**: `composer test` before committing
2. **Check Laravel compatibility**: Ensure changes work with Laravel 10+
3. **Update tests**: Add tests for new features
4. **Update README**: Document new features or changes
5. **Follow existing patterns**: Match the code style and structure

### Important Notes

- This package supports **Non-OAuth** only (API key only)
- YouTube API has quota limits - caching is important
- Some methods return `false` instead of exceptions for "not found" cases
- `parseVidFromURL()` throws `InvalidArgumentException` for invalid URLs
- Cache is only active when Laravel Cache is available

## 🚀 Quick Commands

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run tests with API
YOUTUBE_ENABLED=true composer test

# Code style check
composer inspect

# Auto-fix code style
composer lint

# Generate API docs
composer apidoc
```

## 📝 Notes for AI

- This is a **Laravel package**, not a full Laravel application
- Tests use **Orchestra Testbench** for Laravel integration testing
- The package uses **Facades** but some methods are static-only
- Always check if a method exists on Facade before calling it statically
- When in doubt, use the main `Youtube` class directly instead of Facade

---

**Last Updated**: 2026-06-06
**Maintainer**: cable8mm
**License**: MIT
