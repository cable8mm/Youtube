<?php

namespace Cable8mm\Youtube\Tests\Sync;

use Cable8mm\Youtube\Sync\QuotaBlock;
use Cable8mm\Youtube\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class QuotaBlockTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-25 15:00', 'America/Los_Angeles'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_start_blocks_until_next_midnight_in_los_angeles(): void
    {
        $block = new QuotaBlock;

        $until = $block->start();

        $this->assertSame('2026-10-26 00:00:00 America/Los_Angeles', $until->format('Y-m-d H:i:s e'));
        $this->assertTrue($block->until()->eq($until));
        $this->assertSame('America/Los_Angeles', $block->until()->timezoneName);
    }

    public function test_start_uses_los_angeles_date_even_when_utc_is_a_day_ahead(): void
    {
        // 2026-10-26 02:00 UTC is still 2026-10-25 in Los Angeles.
        Carbon::setTestNow(Carbon::parse('2026-10-26 02:00', 'UTC'));

        $this->assertSame('2026-10-26 00:00:00', (new QuotaBlock)->start()->format('Y-m-d H:i:s'));
    }

    public function test_is_active_follows_start_clear_and_expiry(): void
    {
        $block = new QuotaBlock;

        $this->assertFalse($block->isActive());

        $block->start();
        $this->assertTrue($block->isActive());

        $block->clear();
        $this->assertFalse($block->isActive());
        $this->assertNull($block->until());

        $block->start();
        Carbon::setTestNow(Carbon::parse('2026-10-26 00:00:01', 'America/Los_Angeles'));
        $this->assertFalse($block->isActive());
    }

    public function test_block_is_stored_under_the_documented_cache_key(): void
    {
        (new QuotaBlock)->start();

        $this->assertSame('youtube_quota_blocked_until', QuotaBlock::CACHE_KEY);
        $this->assertTrue(Cache::has('youtube_quota_blocked_until'));
    }

    public function test_block_is_shared_through_the_default_cache_store(): void
    {
        (new QuotaBlock)->start();

        $this->assertTrue((new QuotaBlock)->isActive());
    }

    public function test_without_a_bound_cache_the_block_is_never_active(): void
    {
        $this->app->offsetUnset('cache');

        $block = new QuotaBlock;
        $until = $block->start();

        $this->assertSame('2026-10-26 00:00:00', $until->format('Y-m-d H:i:s'));
        $this->assertFalse($block->isActive());
        $this->assertNull($block->until());
    }
}
