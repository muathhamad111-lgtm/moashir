<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Data\Files\ScanVerdict;
use App\Models\Files\StoredFile;
use App\Services\Files\FileAlerts;
use App\Services\Files\StoredFileScanner;
use Illuminate\Support\Facades\Log;

final readonly class AbandonStoredFileScan
{
    public const string ENGINE = 'scan-abandoned';

    public function __construct(private StoredFileScanner $scanner, private FileAlerts $alerts) {}

    public function handle(int $storedFileId): void
    {
        $file = StoredFile::query()->find($storedFileId);

        if (! $file instanceof StoredFile || ! $this->scanner->record($file, ScanVerdict::failed(self::ENGINE))) {
            return;
        }

        Log::warning('files.scan_abandoned', ['stored_file_id' => $file->id, 'purpose' => $file->purpose->value]);
        $this->alerts->scanAbandoned();
    }
}
