<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Contracts\Files\FileScanner;
use App\Data\Audit\AuditEntry;
use App\Data\Files\ScanVerdict;
use App\Enums\Audit\AuditEvent;
use App\Enums\Files\MalwareScanStatus;
use App\Models\Files\StoredFile;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

final readonly class StoredFileScanner
{
    public function __construct(
        private FileScanner $scanner,
        private StoredFileDisk $disk,
        private AuditRecorder $audit,
    ) {}

    public function scan(StoredFile $file): ScanVerdict
    {
        $verdict = $this->inspect($file);
        $this->record($file, $verdict);

        return $verdict;
    }

    public function inspect(StoredFile $file): ScanVerdict
    {
        $stream = $this->disk->readStream($file);

        try {
            return $this->scanner->scan($stream);
        } finally {
            fclose($stream);
        }
    }

    public function record(StoredFile $file, ScanVerdict $verdict): bool
    {
        $result = [
            'malware_scan_status' => $verdict->status,
            'malware_scanned_at' => Date::now(),
            'scan_engine' => $verdict->engine,
        ];

        $claimed = StoredFile::query()->whereKey($file->id)->awaitingScan()->update($result);

        if ($claimed === 0) {
            return false;
        }

        $file->forceFill($result)->syncOriginalAttributes(array_keys($result));

        $this->audit->record(new AuditEntry(AuditEvent::FileScanned, $file, ['malware_scan_status' => MalwareScanStatus::Pending->value], [
            'malware_scan_status' => $verdict->status->value,
            'scan_engine' => $verdict->engine,
            'signature' => $verdict->signature,
        ]));

        return true;
    }
}
