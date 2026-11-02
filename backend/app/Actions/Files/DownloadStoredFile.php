<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Data\Audit\AuditEntry;
use App\Data\Files\FileDownload;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Files\FileRejected;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Files\StoredFileDisk;

final readonly class DownloadStoredFile
{
    public function __construct(private StoredFileDisk $disk, private AuditRecorder $audit) {}

    public function handle(User $actor, StoredFile $file, int $boundUserId): FileDownload
    {
        if ($boundUserId !== $actor->id) {
            throw FileRejected::signatureInvalid();
        }

        if (! $file->isScannedClean()) {
            throw FileRejected::notClean();
        }

        $stream = $this->disk->readStream($file);

        if ($file->purpose->isConfidential()) {
            $this->audit->record(new AuditEntry(AuditEvent::FileDownloaded, $file, [], [
                'purpose' => $file->purpose->value,
                'attachable_type' => $file->attachable_type,
                'attachable_id' => $file->attachable_id,
            ], ['download']));
        }

        return new FileDownload($stream, $file->original_name, $file->mime_type, $file->size_bytes);
    }
}
