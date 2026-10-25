<?php

namespace Cable8mm\Youtube\Console;

use Cable8mm\Youtube\Imports\YoutubeChannelsImport;
use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;

class ImportChannelsCommand extends Command
{
    protected $signature = 'youtube:import-channels {file : CSV or Excel file with a "channel" column}';

    protected $description = 'Import YouTube channels from a CSV or Excel file';

    public const EXTENSIONS = ['csv', 'xlsx', 'xls'];

    public function handle(): int
    {
        $file = $this->argument('file');
        $path = realpath($file);

        if ($path === false || ! is_file($path)) {
            $this->error("file not found: {$file}");

            return self::FAILURE;
        }

        if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
            $this->error('unsupported file type: use '.implode(', ', self::EXTENSIONS));

            return self::FAILURE;
        }

        $import = new YoutubeChannelsImport;
        Excel::import($import, $path);

        if (! $import->hasChannelColumn()) {
            $this->error(YoutubeChannelsImport::MISSING_CHANNEL_COLUMN);

            return self::FAILURE;
        }

        $results = collect($import->results());

        foreach ($results->countBy('result') as $result => $count) {
            $this->line("{$result}: {$count}");
        }

        $failed = $results->filter(fn ($row) => str_starts_with($row['result'], 'failed: '));

        foreach ($failed as $row) {
            $this->line("row {$row['row']}: {$row['input']}: ".substr($row['result'], strlen('failed: ')));
        }

        return $failed->isEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
