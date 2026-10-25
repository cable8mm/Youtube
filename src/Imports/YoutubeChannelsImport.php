<?php

namespace Cable8mm\Youtube\Imports;

use Cable8mm\Youtube\Exceptions\YoutubeApiException;
use Cable8mm\Youtube\Models\YoutubeChannel;
use Cable8mm\Youtube\Sync\ChannelInputResolver;
use Cable8mm\Youtube\Sync\FailureReason;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Row;

/**
 * Imports channels from the `channel` column of a CSV or Excel file (PRODUCT_SPEC.md §17).
 *
 * Usage: Excel::import(new YoutubeChannelsImport($owner), $file), then results().
 */
class YoutubeChannelsImport implements OnEachRow, SkipsEmptyRows, WithMultipleSheets
{
    public const MISSING_CHANNEL_COLUMN = 'missing channel column';

    /** @var list<array{row: int, input: string, result: string}> */
    protected array $results = [];

    protected ?int $channelColumn = null;

    protected bool $headerRead = false;

    protected bool $quotaExceeded = false;

    public function __construct(
        protected ?Model $owner = null,
        protected ?ChannelInputResolver $resolver = null,
    ) {
        $this->resolver ??= new ChannelInputResolver;
    }

    /**
     * Only the first sheet is read.
     */
    public function sheets(): array
    {
        return [0 => $this];
    }

    public function onRow(Row $row): void
    {
        $cells = $row->toArray();

        if (! $this->headerRead) {
            $this->readHeader($cells);

            return;
        }

        if ($this->channelColumn === null) {
            return;
        }

        $input = trim((string) ($cells[$this->channelColumn] ?? ''));

        $this->results[] = [
            'row' => $row->getIndex(),
            'input' => $input,
            'result' => $this->quotaExceeded ? 'skipped: quota exceeded' : $this->import($input),
        ];
    }

    /**
     * @return list<array{row: int, input: string, result: string}>
     */
    public function results(): array
    {
        return $this->results;
    }

    /**
     * False when the file has no `channel` header, in which case nothing was imported.
     */
    public function hasChannelColumn(): bool
    {
        return $this->channelColumn !== null;
    }

    protected function readHeader(array $cells): void
    {
        $this->headerRead = true;

        foreach ($cells as $index => $cell) {
            if (strtolower(trim((string) $cell)) === 'channel') {
                $this->channelColumn = $index;

                return;
            }
        }
    }

    protected function import(string $input): string
    {
        try {
            $channel = $this->resolver->resolve($input);
        } catch (YoutubeApiException $e) {
            $reason = FailureReason::fromThrowable($e);
            $this->quotaExceeded = $reason === FailureReason::QUOTA_EXCEEDED;

            return 'failed: '.$reason;
        }

        if (is_string($channel)) {
            return 'failed: '.$channel;
        }

        $record = YoutubeChannel::firstOrNew(['channel_id' => $channel->id]);
        $created = ! $record->exists;

        $record->fill([
            'handle' => $channel->snippet->customUrl ?? null,
            'title' => $channel->snippet->title ?? $channel->id,
            'uploads_playlist_id' => $channel->contentDetails->relatedPlaylists->uploads ?? null,
        ]);

        if ($this->owner !== null) {
            $record->owner()->associate($this->owner);
        }

        $record->save();

        return $created ? 'created' : 'updated';
    }
}
