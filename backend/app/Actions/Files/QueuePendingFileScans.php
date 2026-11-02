<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Jobs\Files\ScanStoredFileJob;
use App\Models\Files\StoredFile;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Date;

final readonly class QueuePendingFileScans
{
    private const int CHUNK = 500;

    public function __construct(private Repository $config) {}

    public function handle(): int
    {
        $cutoff = Date::now()->subMinutes($this->config->integer('moashir.files.scan_retry_after_minutes'));
        $queued = 0;

        StoredFile::query()
            ->awaitingScan()
            ->where('created_at', '<', $cutoff)
            ->select('id')
            ->lazyById(self::CHUNK)
            ->each(static function (StoredFile $file) use (&$queued): void {
                ScanStoredFileJob::dispatch($file->id);
                $queued++;
            });

        return $queued;
    }
}
