<?php

declare(strict_types=1);

use App\Enums\Files\FileVisibility;
use App\Enums\Files\MalwareScanStatus;
use App\Enums\Files\StoredFilePurpose;
use App\Integrations\Fakes\FakeFileScanner;
use App\Models\Files\StoredFile;
use App\Services\Files\StoredFileDisk;
use Illuminate\Support\Facades\Date;

const SAMPLE_PDF = "%PDF-1.4\n%Moashir bank transfer receipt\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << >> >> endobj\nxref\n0 4\n0000000000 65535 f \n0000000040 00000 n \n0000000089 00000 n \n0000000146 00000 n \ntrailer << /Size 4 /Root 1 0 R >>\nstartxref\n234\n%%EOF\n";

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
