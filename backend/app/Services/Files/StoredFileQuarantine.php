<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Files\StoredFile;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

final readonly class StoredFileQuarantine
{
    public function __construct(private StoredFileDisk $disk, private AuditRecorder $audit) {}

    public function isolate(StoredFile $file): bool
    {
        if (! $file->needsQuarantine()) {
            return false;
        }

        $isolated = ['path' => $this->disk->moveIntoQuarantine($file), 'quarantined_at' => Date::now()];
        $claimed = StoredFile::query()->whereKey($file->id)->whereNull('quarantined_at')->update($isolated);

        if ($claimed === 0) {
            return false;
        }

        $file->forceFill($isolated)->syncOriginalAttributes(array_keys($isolated));

        $this->audit->record(new AuditEntry(AuditEvent::FileQuarantined, $file, ['quarantined' => false], [
            'quarantined' => true,
            'purpose' => $file->purpose->value,
            'scan_engine' => $file->scan_engine,
        ]));

        return true;
    }
}
