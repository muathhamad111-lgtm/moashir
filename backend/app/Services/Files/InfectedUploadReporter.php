<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Files\StoredFile;
use App\Services\Audit\AuditRecorder;

final readonly class InfectedUploadReporter
{
    public function __construct(private AuditRecorder $audit, private FileAlerts $alerts) {}

    public function report(StoredFile $refused): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::InfectedUploadRefused, $refused, [], [
            'purpose' => $refused->purpose->value,
            'size_bytes' => $refused->size_bytes,
        ]));

        $this->alerts->infectedUploadRefused($refused->purpose);
    }
}
