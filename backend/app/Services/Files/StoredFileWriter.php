<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Data\Files\GeneratedFile;
use App\Enums\Files\MalwareScanStatus;
use App\Models\Files\StoredFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use RuntimeException;

final readonly class StoredFileWriter
{
    public const string GENERATED_ENGINE = 'generated';

    public function __construct(private StoredFileDisk $disk) {}

    public function storeGenerated(GeneratedFile $generated, Model $owner): StoredFile
    {
        $path = $this->disk->newPath($generated->purpose, $generated->extension);
        $this->disk->put($path, $generated->contents);

        return $this->record($generated, $path, strlen($generated->contents), hash('sha256', $generated->contents), $owner);
    }

    public function storeGeneratedFromPath(GeneratedFile $generated, string $localPath, Model $owner): StoredFile
    {
        $path = $this->disk->newPath($generated->purpose, $generated->extension);
        $stream = fopen($localPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('The generated file could not be read.');
        }

        try {
            $this->disk->putStream($path, $stream);
        } finally {
            fclose($stream);
        }

        return $this->record($generated, $path, (int) filesize($localPath), (string) hash_file('sha256', $localPath), $owner);
    }

    private function record(GeneratedFile $generated, string $path, int $size, string $sha256, Model $owner): StoredFile
    {
        $file = new StoredFile([
            'purpose' => $generated->purpose,
            'disk' => $this->disk->name(),
            'path' => $path,
            'original_name' => $generated->fileName,
            'extension' => $generated->extension,
            'mime_type' => $generated->mimeType,
            'size_bytes' => $size,
            'sha256' => $sha256,
            'visibility' => $generated->purpose->visibility(),
            'content_class' => $generated->purpose->contentClass(),
            'malware_scan_status' => MalwareScanStatus::Clean,
            'malware_scanned_at' => Date::now(),
            'scan_engine' => self::GENERATED_ENGINE,
        ]);
        $file->attachable()->associate($owner);
        $file->save();

        return $file;
    }
}
