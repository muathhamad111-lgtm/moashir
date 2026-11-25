<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Data\Audit\AuditEntry;
use App\Data\Files\UploadCandidate;
use App\Enums\Audit\AuditEvent;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use Illuminate\Http\UploadedFile;

final readonly class FileIntake
{
    private const int MAX_NAME_LENGTH = 200;

    public function __construct(
        private StoredFileDisk $disk,
        private StoredFileScanner $scanner,
        private UploadContentCheck $contentCheck,
        private ScannedUploadRefusal $refusal,
        private AuditRecorder $audit,
    ) {}

    /**
     * The uploader is null only for a guest's upload, such as the documents of an entity registration.
     */
    public function accept(UploadedFile $upload, StoredFilePurpose $purpose, ?User $uploader, string $field): StoredFile
    {
        $candidate = $this->candidateOf($upload);
        $this->contentCheck->assertAcceptable($candidate, $purpose->uploadTypes(), $field);

        $file = $this->store($upload, $purpose, $uploader, $candidate);
        $status = $this->scanner->scan($file)->status;

        if ($status !== MalwareScanStatus::Clean) {
            throw $this->refusal->refuse($file, $status, $field);
        }

        return $file;
    }

    /**
     * @param  array<string, list<string>>  $allowedTypes
     */
    public function acceptForBackgroundScan(UploadedFile $upload, StoredFilePurpose $purpose, User $uploader, string $field, array $allowedTypes): StoredFile
    {
        $candidate = $this->candidateOf($upload);
        $this->contentCheck->assertAcceptable($candidate, $allowedTypes, $field);

        return $this->store($upload, $purpose, $uploader, $candidate);
    }

    private function candidateOf(UploadedFile $upload): UploadCandidate
    {
        return new UploadCandidate(
            path: (string) $upload->getRealPath(),
            contents: (string) $upload->get(),
            extension: mb_strtolower($upload->getClientOriginalExtension()),
            mimeType: (string) $upload->getMimeType(),
        );
    }

    private function store(UploadedFile $upload, StoredFilePurpose $purpose, ?User $uploader, UploadCandidate $candidate): StoredFile
    {
        $path = $this->disk->newPath($purpose, $candidate->extension);
        $this->disk->put($path, $candidate->contents);

        $file = StoredFile::query()->create([
            'purpose' => $purpose,
            'disk' => $this->disk->name(),
            'path' => $path,
            'original_name' => $this->safeName($upload->getClientOriginalName()),
            'extension' => $candidate->extension === '' ? null : $candidate->extension,
            'mime_type' => $candidate->mimeType,
            'size_bytes' => strlen($candidate->contents),
            'sha256' => hash('sha256', $candidate->contents),
            'visibility' => $purpose->visibility(),
            'content_class' => $purpose->contentClass(),
            'malware_scan_status' => MalwareScanStatus::Pending,
            'uploaded_by_user_id' => $uploader?->id,
        ]);

        $this->audit->record(new AuditEntry(AuditEvent::FileUploaded, $file, [], [
            'purpose' => $purpose->value,
            'mime_type' => $candidate->mimeType,
            'size_bytes' => $file->size_bytes,
            'sha256' => $file->sha256,
        ]));

        return $file;
    }

    private function safeName(string $name): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', $name) ?? '';

        return mb_substr(trim($clean), 0, self::MAX_NAME_LENGTH) ?: 'file';
    }
}
