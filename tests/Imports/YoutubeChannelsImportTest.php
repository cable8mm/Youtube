<?php

namespace Cable8mm\Youtube\Tests\Imports;

use Cable8mm\Youtube\Imports\YoutubeChannelsImport;
use Cable8mm\Youtube\Models\YoutubeChannel;
use Cable8mm\Youtube\Tests\Models\Fixtures\Artist;
use Cable8mm\Youtube\Tests\Resources\FakeApiClient;
use Cable8mm\Youtube\Tests\Resources\FakeYoutube;
use Cable8mm\Youtube\Tests\TestCase;
use Cable8mm\Youtube\Youtube;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class YoutubeChannelsImportTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURES = __DIR__.'/../fixtures';

    private FakeApiClient $fake;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Schema::create('artists', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->fake = new FakeApiClient;
        $this->app->instance(Youtube::class, new FakeYoutube($this->fake));
    }

    /**
     * A channels.list body with one channel (documented channels resource shape).
     */
    private static function channelBody(string $id, string $title, string $customUrl): string
    {
        return json_encode([
            'kind' => 'youtube#channelListResponse',
            'etag' => 'e',
            'items' => [[
                'kind' => 'youtube#channel',
                'id' => $id,
                'snippet' => ['title' => $title, 'customUrl' => $customUrl],
                'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU'.substr($id, 2)]],
            ]],
        ]);
    }

    private static function errorBody(int $code, string $message, string $reason): string
    {
        return json_encode(['error' => ['code' => $code, 'message' => $message, 'errors' => [['reason' => $reason]]]]);
    }

    /**
     * Bodies for the three resolvable rows of tests/fixtures/channels.csv, in order.
     */
    private function queueFixtureChannels(): void
    {
        array_push(
            $this->fake->responses,
            self::channelBody('UCk1SpWNzOs4MYmr0uICEntg', 'XDA', '@xda'),
            self::channelBody('UCK8sQmJBp8GCxrOtXWBpyEA', 'Google', '@google'),
            self::channelBody('UCK8sQmJBp8GCxrOtXWBpyEA', 'Google', '@google'),
        );
    }

    private function import(string $file, ?Artist $owner = null): YoutubeChannelsImport
    {
        $import = new YoutubeChannelsImport($owner);
        Excel::import($import, self::FIXTURES.'/'.$file);

        return $import;
    }

    private function expectedFirstRun(): array
    {
        return [
            ['row' => 2, 'input' => 'UCk1SpWNzOs4MYmr0uICEntg', 'result' => 'created'],
            ['row' => 3, 'input' => '@Google', 'result' => 'created'],
            ['row' => 4, 'input' => 'https://www.youtube.com/user/Google', 'result' => 'updated'],
            ['row' => 5, 'input' => 'https://www.youtube.com/c/Google', 'result' => 'failed: unsupported input'],
        ];
    }

    // ==================== Import object ====================

    public function test_csv_import_creates_channel_records(): void
    {
        $this->queueFixtureChannels();

        $import = $this->import('channels.csv');

        $this->assertTrue($import->hasChannelColumn());
        $this->assertSame($this->expectedFirstRun(), $import->results());
        $this->assertSame(2, YoutubeChannel::count());

        $xda = YoutubeChannel::where('channel_id', 'UCk1SpWNzOs4MYmr0uICEntg')->first();
        $this->assertSame('XDA', $xda->title);
        $this->assertSame('@xda', $xda->handle);
        $this->assertSame('UUk1SpWNzOs4MYmr0uICEntg', $xda->uploads_playlist_id);
        $this->assertTrue($xda->is_active);
        $this->assertNull($xda->owner_type);
    }

    public function test_xlsx_import_reads_only_the_first_sheet(): void
    {
        $this->queueFixtureChannels();

        $import = $this->import('channels.xlsx');

        $this->assertSame($this->expectedFirstRun(), $import->results());
        $this->assertCount(3, $this->fake->requests);
        $this->assertSame(2, YoutubeChannel::count());
    }

    public function test_reimport_updates_and_keeps_sync_state_and_videos(): void
    {
        $this->queueFixtureChannels();
        $this->import('channels.csv');

        $xda = YoutubeChannel::where('channel_id', 'UCk1SpWNzOs4MYmr0uICEntg')->first();
        $xda->update(['is_active' => false, 'sync_status' => 'failed']);
        $xda->videos()->create(['video_id' => 'v1', 'title' => 'Video', 'published_at' => '2026-01-01 00:00:00']);

        $this->queueFixtureChannels();
        $import = $this->import('channels.csv');

        $this->assertSame(
            ['updated', 'updated', 'updated', 'failed: unsupported input'],
            array_column($import->results(), 'result')
        );
        $this->assertSame(2, YoutubeChannel::count());

        $xda->refresh();
        $this->assertFalse($xda->is_active);
        $this->assertSame('failed', $xda->sync_status);
        $this->assertSame(1, $xda->videos()->count());
    }

    public function test_owner_is_set_on_created_and_updated_records(): void
    {
        $artist = Artist::create(['name' => 'Artist']);
        $this->queueFixtureChannels();

        $this->import('channels.csv', $artist);

        $this->assertSame(
            ['UCK8sQmJBp8GCxrOtXWBpyEA', 'UCk1SpWNzOs4MYmr0uICEntg'],
            $artist->youtubeChannels()->orderBy('channel_id')->pluck('channel_id')->all()
        );
    }

    public function test_file_without_channel_column_imports_nothing(): void
    {
        $import = $this->import('channels_without_channel_column.csv');

        $this->assertFalse($import->hasChannelColumn());
        $this->assertSame([], $import->results());
        $this->assertSame([], $this->fake->requests);
        $this->assertSame(0, YoutubeChannel::count());
    }

    public function test_quota_exceeded_skips_the_remaining_rows(): void
    {
        array_push(
            $this->fake->responses,
            self::channelBody('UCk1SpWNzOs4MYmr0uICEntg', 'XDA', '@xda'),
            self::errorBody(403, 'The request cannot be completed because you have exceeded your quota.', 'quotaExceeded'),
        );

        $import = $this->import('channels.csv');

        $this->assertSame(
            ['created', 'failed: quota_exceeded', 'skipped: quota exceeded', 'skipped: quota exceeded'],
            array_column($import->results(), 'result')
        );
        $this->assertCount(2, $this->fake->requests);
    }

    public function test_other_api_errors_fail_the_row_and_continue(): void
    {
        array_push(
            $this->fake->responses,
            self::errorBody(400, 'API key not valid. Please pass a valid API key.', 'badRequest'),
            self::channelBody('UCK8sQmJBp8GCxrOtXWBpyEA', 'Google', '@google'),
            self::channelBody('UCK8sQmJBp8GCxrOtXWBpyEA', 'Google', '@google'),
        );

        $import = $this->import('channels.csv');

        $this->assertSame(
            ['failed: invalid_key', 'created', 'updated', 'failed: unsupported input'],
            array_column($import->results(), 'result')
        );
    }

    public function test_channel_not_found_fails_the_row(): void
    {
        // An empty channels.list body for the first row; the rest resolve.
        array_push(
            $this->fake->responses,
            '{"kind":"youtube#channelListResponse","etag":"e","items":[]}',
            self::channelBody('UCK8sQmJBp8GCxrOtXWBpyEA', 'Google', '@google'),
            self::channelBody('UCK8sQmJBp8GCxrOtXWBpyEA', 'Google', '@google'),
        );

        $results = $this->import('channels.csv')->results();

        $this->assertSame('failed: channel not found', $results[0]['result']);
        $this->assertSame(1, YoutubeChannel::count());
    }

    // ==================== Command ====================

    public function test_command_prints_counts_and_failed_rows_and_exits_1(): void
    {
        $this->queueFixtureChannels();

        $this->artisan('youtube:import-channels', ['file' => self::FIXTURES.'/channels.csv'])
            ->expectsOutput('created: 2')
            ->expectsOutput('updated: 1')
            ->expectsOutput('failed: unsupported input: 1')
            ->expectsOutput('row 5: https://www.youtube.com/c/Google: unsupported input')
            ->assertExitCode(1);
    }

    public function test_command_exits_0_when_no_row_failed(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'channels').'.csv';
        file_put_contents($file, "channel\n@Google\n");
        $this->fake->responses[] = self::channelBody('UCK8sQmJBp8GCxrOtXWBpyEA', 'Google', '@google');

        try {
            $this->artisan('youtube:import-channels', ['file' => $file])
                ->expectsOutput('created: 1')
                ->assertExitCode(0);
        } finally {
            unlink($file);
        }
    }

    public function test_command_reports_missing_channel_column(): void
    {
        $this->artisan('youtube:import-channels', ['file' => self::FIXTURES.'/channels_without_channel_column.csv'])
            ->expectsOutput('missing channel column')
            ->assertExitCode(1);
    }

    public function test_command_rejects_missing_files_and_other_extensions(): void
    {
        $this->artisan('youtube:import-channels', ['file' => self::FIXTURES.'/nope.csv'])
            ->expectsOutputToContain('file not found')
            ->assertExitCode(1);

        $this->artisan('youtube:import-channels', ['file' => __FILE__])
            ->expectsOutputToContain('unsupported file type')
            ->assertExitCode(1);

        $this->assertSame([], $this->fake->requests);
    }
}
