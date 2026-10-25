<?php

namespace Cable8mm\Youtube\Tests\E2E;

use Cable8mm\Youtube\Models\YoutubeChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;

class ChannelImportTest extends TestCase
{
    use RefreshDatabase;

    private const FIXTURES = __DIR__.'/../fixtures';

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public static function files(): array
    {
        return [
            'csv' => ['channels.csv'],
            'xlsx' => ['channels.xlsx'],
        ];
    }

    #[DataProvider('files')]
    public function test_import_creates_then_updates_channel_records(string $file): void
    {
        $this->artisan('youtube:import-channels', ['file' => self::FIXTURES.'/'.$file])
            ->expectsOutput('created: 2')
            ->expectsOutput('updated: 1')
            ->expectsOutput('row 5: https://www.youtube.com/c/Google: unsupported input')
            ->assertExitCode(1);

        $this->assertSame(
            ['UCK8sQmJBp8GCxrOtXWBpyEA', 'UCk1SpWNzOs4MYmr0uICEntg'],
            YoutubeChannel::orderBy('channel_id')->pluck('channel_id')->all()
        );

        foreach (YoutubeChannel::all() as $channel) {
            $this->assertNotEmpty($channel->title);
            $this->assertStringStartsWith('UU', $channel->uploads_playlist_id);
        }

        $this->artisan('youtube:import-channels', ['file' => self::FIXTURES.'/'.$file])
            ->expectsOutput('updated: 3')
            ->assertExitCode(1);

        $this->assertSame(2, YoutubeChannel::count());
    }
}
