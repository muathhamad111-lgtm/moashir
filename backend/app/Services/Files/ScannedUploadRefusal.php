<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Enums\Files\MalwareScanStatus;
use App\Exceptions\Files\FileRejected;
use App\Models\Files\StoredFile;

final readonly class ScannedUploadRefusal
{
    public function __construct(private StoredFileRemover $remover, private InfectedUploadReporter $infectedUploads) {}

    public function refuse(StoredFile $file, MalwareScanStatus $status, string $field): FileRejected
    {
        $this->remover->remove($file);

        if ($status !== MalwareScanStatus::Infected) {
            return FileRejected::scanFailed($field);
        }

        $this->infectedUploads->report($file);

        return FileRejected::infected($field);
    }
}
