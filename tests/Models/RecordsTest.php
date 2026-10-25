<?php

namespace Cable8mm\Youtube\Tests\Models;

use Cable8mm\Youtube\Models\YoutubeChannel;
use Cable8mm\Youtube\Models\YoutubeSyncError;
use Cable8mm\Youtube\Models\YoutubeVideo;
use Cable8mm\Youtube\Tests\Models\Fixtures\Artist;
use Cable8mm\Youtube\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class RecordsTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Testbench's in-memory SQLite connection leaves foreign keys off; Laravel apps turn them on.
        $app['config']->set('database.connections.testing.foreign_key_constraints', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Schema::create('artists', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * @return array<string, array{type: string, nullable: bool, default: mixed}>
     */
    private function columns(string $table): array
    {
        $columns = [];

        foreach (Schema::getColumns($table) as $column) {
            $columns[$column['name']] = [
                'type' => $column['type_name'],
                'nullable' => $column['nullable'],
                'default' => $column['default'],
            ];
        }

        return $columns;
    }

    private function uniqueColumns(string $table): array
    {
        return collect(Schema::getIndexes($table))
            ->filter(fn ($index) => $index['unique'] && ! $index['primary'])
            ->pluck('columns')
            ->all();
    }

    private function foreignKeys(string $table): array
    {
        return collect(Schema::getForeignKeys($table))
            ->mapWithKeys(fn ($key) => [$key['columns'][0] => [$key['foreign_table'], strtolower($key['on_delete'])]])
            ->all();
    }

    private function channel(array $attributes = []): YoutubeChannel
    {
        return YoutubeChannel::create($attributes + [
            'channel_id' => 'UCk1SpWNzOs4MYmr0uICEntg',
            'title' => 'XDA',
        ]);
    }

    // ==================== Schema ====================

    public function test_youtube_channels_table_matches_spec(): void
    {
        $columns = $this->columns('youtube_channels');

        $this->assertSame([
            'id', 'channel_id', 'handle', 'title', 'uploads_playlist_id', 'owner_type', 'owner_id',
            'is_active', 'sync_status', 'last_synced_at', 'last_error_reason', 'created_at', 'updated_at',
        ], array_keys($columns));

        foreach (['handle', 'uploads_playlist_id', 'owner_type', 'owner_id', 'sync_status', 'last_synced_at', 'last_error_reason'] as $name) {
            $this->assertTrue($columns[$name]['nullable'], "$name should be nullable");
        }

        foreach (['channel_id', 'title', 'is_active'] as $name) {
            $this->assertFalse($columns[$name]['nullable'], "$name should not be nullable");
        }

        $this->assertSame([['channel_id']], $this->uniqueColumns('youtube_channels'));
        $this->assertTrue($this->channel()->refresh()->is_active);
    }

    public function test_youtube_videos_table_matches_spec(): void
    {
        $columns = $this->columns('youtube_videos');

        $this->assertSame([
            'id', 'youtube_channel_id', 'video_id', 'title', 'description', 'published_at', 'duration',
            'thumbnail_url', 'view_count', 'like_count', 'comment_count', 'unavailable_at', 'created_at', 'updated_at',
        ], array_keys($columns));

        foreach (['description', 'duration', 'thumbnail_url', 'view_count', 'like_count', 'comment_count', 'unavailable_at'] as $name) {
            $this->assertTrue($columns[$name]['nullable'], "$name should be nullable");
        }

        foreach (['youtube_channel_id', 'video_id', 'title', 'published_at'] as $name) {
            $this->assertFalse($columns[$name]['nullable'], "$name should not be nullable");
        }

        $this->assertSame([['video_id']], $this->uniqueColumns('youtube_videos'));
        $this->assertSame(['youtube_channel_id' => ['youtube_channels', 'cascade']], $this->foreignKeys('youtube_videos'));
    }

    public function test_youtube_sync_errors_table_matches_spec(): void
    {
        $columns = $this->columns('youtube_sync_errors');

        $this->assertSame([
            'id', 'youtube_channel_id', 'reason', 'http_code', 'message', 'created_at', 'updated_at',
        ], array_keys($columns));

        $this->assertTrue($columns['youtube_channel_id']['nullable']);
        $this->assertTrue($columns['http_code']['nullable']);
        $this->assertFalse($columns['reason']['nullable']);
        $this->assertFalse($columns['message']['nullable']);

        $this->assertSame(['youtube_channel_id' => ['youtube_channels', 'set null']], $this->foreignKeys('youtube_sync_errors'));
    }

    // ==================== Relations ====================

    public function test_relations_return_related_records(): void
    {
        $channel = $this->channel();
        $video = $channel->videos()->create([
            'video_id' => 'rie-hPVJ7Sw',
            'title' => 'Video',
            'published_at' => '2013-02-28 00:00:00',
        ]);
        $error = $channel->syncErrors()->create(['reason' => 'invalid_key', 'http_code' => 400, 'message' => 'Error 400']);

        $this->assertTrue($channel->videos->first()->is($video));
        $this->assertTrue($channel->syncErrors->first()->is($error));
        $this->assertTrue($video->channel->is($channel));
        $this->assertTrue($error->channel->is($channel));
        $this->assertSame(400, $error->http_code);
    }

    public function test_owner_relation_and_trait(): void
    {
        $artist = Artist::create(['name' => 'Artist']);
        $owned = $this->channel();
        $owned->owner()->associate($artist)->save();
        $this->channel(['channel_id' => 'UCK8sQmJBp8GCxrOtXWBpyEA', 'title' => 'Google']);

        $this->assertTrue($owned->refresh()->owner->is($artist));
        $this->assertSame(['UCk1SpWNzOs4MYmr0uICEntg'], $artist->youtubeChannels->pluck('channel_id')->all());
        $this->assertNull(YoutubeChannel::where('channel_id', 'UCK8sQmJBp8GCxrOtXWBpyEA')->first()->owner);
    }

    public function test_deleting_a_channel_deletes_videos_and_detaches_sync_errors(): void
    {
        $channel = $this->channel();
        $channel->videos()->create(['video_id' => 'rie-hPVJ7Sw', 'title' => 'Video', 'published_at' => '2013-02-28 00:00:00']);
        $error = $channel->syncErrors()->create(['reason' => 'unknown', 'message' => 'boom']);

        $channel->delete();

        $this->assertSame(0, YoutubeVideo::count());
        $this->assertNull($error->refresh()->youtube_channel_id);
        $this->assertSame(1, YoutubeSyncError::count());
    }

    // ==================== Publishing and settings ====================

    public function test_vendor_publish_copies_the_migrations(): void
    {
        $target = database_path('migrations');
        $before = File::exists($target) ? File::files($target) : [];

        $this->artisan('vendor:publish', ['--tag' => 'youtube-migrations'])->assertSuccessful();

        $published = collect(File::files($target))
            ->reject(fn ($file) => in_array($file, $before))
            ->map(fn ($file) => $file->getFilename());

        try {
            foreach (['youtube_channels', 'youtube_videos', 'youtube_sync_errors'] as $table) {
                $this->assertTrue(
                    $published->contains(fn ($name) => str_ends_with($name, "_create_{$table}_table.php")),
                    "migration for $table was not published"
                );
            }
        } finally {
            $published->each(fn ($name) => File::delete($target.'/'.$name));
        }
    }

    public function test_sync_settings_have_defaults(): void
    {
        $this->assertNull(config('youtube.sync.queue'));
        $this->assertSame(1, config('youtube.sync.max_pages'));
    }

    public function test_sync_queue_reads_environment_variable(): void
    {
        $_ENV['YOUTUBE_SYNC_QUEUE'] = 'media';

        try {
            $config = require __DIR__.'/../../src/config/youtube.php';

            $this->assertSame('media', $config['sync']['queue']);
        } finally {
            unset($_ENV['YOUTUBE_SYNC_QUEUE']);
        }
    }
}
