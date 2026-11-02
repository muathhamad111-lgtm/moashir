<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Enums\Files\FileScanAttempt;
use App\Enums\Files\MalwareScanStatus;
use App\Events\Files\StoredFileQuarantined;
use App\Models\Files\StoredFile;
use App\Services\Files\FileAlerts;
use App\Services\Files\StoredFileQuarantine;
use App\Services\Files\StoredFileScanner;

final readonly class ScanPendingStoredFile
{
    public function __construct(
        private StoredFileScanner $scanner,
        private StoredFileQuarantine $quarantine,
        private FileAlerts $alerts,
    ) {}

    public function handle(int $storedFileId): FileScanAttempt
    {
        $file = StoredFile::query()->find($storedFileId);

        if (! $file instanceof StoredFile) {
            return FileScanAttempt::Settled;
        }

        if ($file->isAwaitingScan() && ! $this->conclude($file)) {
            return FileScanAttempt::ScannerUnavailable;
        }

        if ($this->quarantine->isolate($file)) {
            $this->alerts->quarantined($file);
            StoredFileQuarantined::dispatch($file);
        }

        return FileScanAttempt::Settled;
    }

    private function conclude(StoredFile $file): bool
    {
        $verdict = $this->scanner->inspect($file);

        if ($verdict->status === MalwareScanStatus::Failed) {
            return false;
        }

        if (! $this->scanner->record($file, $verdict)) {
            $file->refresh();
        }

        return true;
    }
}
