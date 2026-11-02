<?php

declare(strict_types=1);

use App\Enums\Files\FileVisibility;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Integrations\Fakes\FakeFileScanner;
use App\Models\Files\StoredFile;
use App\Services\Files\StoredFileDisk;
use Illuminate\Support\Facades\Date;

const SAMPLE_PDF = "%PDF-1.4\n%Moashir bank transfer receipt\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

/**
 * @param  array<string, mixed>  $attributes
 */
function storedTestFile(array $attributes = [], string $contents = SAMPLE_PDF): StoredFile
{
    $purpose = $attributes['purpose'] ?? StoredFilePurpose::RefundEvidence;
    $disk = app(StoredFileDisk::class);
    $path = $disk->newPath($purpose, 'pdf');
    $disk->put($path, $contents);

    $file = new StoredFile;
    $file->forceFill([
        'purpose' => $purpose,
        'disk' => $disk->name(),
        'path' => $path,
        'original_name' => 'إيصال-تحويل-جمعية-الأمل.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => strlen($contents),
        'sha256' => hash('sha256', $contents),
        'visibility' => FileVisibility::Private,
        'content_class' => 'C',
        'malware_scan_status' => MalwareScanStatus::Pending,
        ...$attributes,
    ])->save();

    return $file->refresh();
}

function infectedContents(): string
{
    return SAMPLE_PDF.FakeFileScanner::TEST_SIGNATURE;
}

function fileScanner(): FakeFileScanner
{
    return app(FakeFileScanner::class);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function storedFileCreatedHoursAgo(int $hours, array $attributes = []): StoredFile
{
    return storedTestFile(['created_at' => Date::now()->subHours($hours), ...$attributes]);
}
