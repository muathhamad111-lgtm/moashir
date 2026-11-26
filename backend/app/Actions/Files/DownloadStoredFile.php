<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Data\Audit\AuditEntry;
use App\Data\Files\DownloadClaim;
use App\Data\Files\FileDownload;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Files\FileRejected;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Files\StoredFileDisk;
use App\Services\Watermark\ViewerStampRule;
use App\Services\Watermark\WatermarkedFileDelivery;

final readonly class DownloadStoredFile
{
    public function __construct(
        private StoredFileDisk $disk,
        private AuditRecorder $audit,
        private WatermarkedFileDelivery $watermarked,
        private ViewerStampRule $stampRule,
    ) {}

    public function handle(User $actor, StoredFile $file, DownloadClaim $claim): FileDownload
    {
        if ($claim->boundUserId !== $actor->id) {
            throw FileRejected::signatureInvalid();
        }

        if (! $file->isScannedClean()) {
            throw FileRejected::notClean();
        }

        if ($this->stampRule->servesStampedCopy($actor, $file->purpose)) {
            return $this->watermarked->deliver($actor, $file, $claim->intent);
        }

        return $this->original($file);
    }

    private function original(StoredFile $file): FileDownload
    {
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
