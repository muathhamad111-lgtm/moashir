<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Exceptions\Files\FileRejected;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Support\Files\ContentSignature;
use Illuminate\Http\UploadedFile;

final readonly class FileIntake
{
    private const int SIGNATURE_BYTES = 16;

    private const int MAX_NAME_LENGTH = 200;

    public function __construct(
        private StoredFileDisk $disk,
        private StoredFileScanner $scanner,
        private StoredFileRemover $remover,
        private InfectedUploadReporter $infectedUploads,
        private AuditRecorder $audit,
    ) {}

    public function accept(UploadedFile $upload, StoredFilePurpose $purpose, User $uploader, string $field): StoredFile
    {
        $contents = (string) $upload->get();
        $extension = mb_strtolower($upload->getClientOriginalExtension());
        $mimeType = (string) $upload->getMimeType();

        if (! $this->isAllowed($purpose, $extension, $mimeType, $contents)) {
            throw FileRejected::contentMismatch($field);
        }

        $file = $this->store($upload, $purpose, $uploader, $contents, $extension, $mimeType);
        $status = $this->scanner->scan($file)->status;

        if ($status !== MalwareScanStatus::Clean) {
            throw $this->refuse($file, $status, $field);
        }

        return $file;
    }

    /**
     * @param  array<string, list<string>>  $allowedTypes
     */
    public function acceptForBackgroundScan(UploadedFile $upload, StoredFilePurpose $purpose, User $uploader, string $field, array $allowedTypes): StoredFile
    {
        $contents = (string) $upload->get();
        $extension = mb_strtolower($upload->getClientOriginalExtension());
        $mimeType = (string) $upload->getMimeType();

        if (! $this->matchesTypes($allowedTypes, $extension, $mimeType, $contents)) {
            throw FileRejected::contentMismatch($field);
        }

        return $this->store($upload, $purpose, $uploader, $contents, $extension, $mimeType);
    }

    private function refuse(StoredFile $file, MalwareScanStatus $status, string $field): FileRejected
    {
        $this->remover->remove($file);

        if ($status !== MalwareScanStatus::Infected) {
            return FileRejected::scanFailed($field);
        }

        $this->infectedUploads->report($file);

        return FileRejected::infected($field);
    }

    private function isAllowed(StoredFilePurpose $purpose, string $extension, string $mimeType, string $contents): bool
    {
        return $this->matchesTypes($purpose->uploadTypes(), $extension, $mimeType, $contents);
    }

    /**
     * @param  array<string, list<string>>  $types
     */
    private function matchesTypes(array $types, string $extension, string $mimeType, string $contents): bool
    {
        return array_key_exists($extension, $types)
            && in_array($mimeType, $types[$extension], true)
            && ContentSignature::matches($mimeType, substr($contents, 0, self::SIGNATURE_BYTES));
    }

    private function store(UploadedFile $upload, StoredFilePurpose $purpose, User $uploader, string $contents, string $extension, string $mimeType): StoredFile
    {
        $path = $this->disk->newPath($purpose, $extension);
        $this->disk->put($path, $contents);

        $file = StoredFile::query()->create([
            'purpose' => $purpose,
            'disk' => $this->disk->name(),
            'path' => $path,
            'original_name' => $this->safeName($upload->getClientOriginalName()),
            'extension' => $extension === '' ? null : $extension,
            'mime_type' => $mimeType,
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
            'visibility' => $purpose->visibility(),
            'content_class' => $purpose->contentClass(),
            'malware_scan_status' => MalwareScanStatus::Pending,
            'uploaded_by_user_id' => $uploader->id,
        ]);

        $this->audit->record(new AuditEntry(AuditEvent::FileUploaded, $file, [], [
            'purpose' => $purpose->value,
            'mime_type' => $mimeType,
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
