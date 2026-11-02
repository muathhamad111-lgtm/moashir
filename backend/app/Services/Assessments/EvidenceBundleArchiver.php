<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Files\GeneratedFile;
use App\Enums\Files\StoredFilePurpose;
use App\Models\Assessments\EvidenceBundle;
use App\Models\Assessments\EvidenceFile;
use App\Models\Files\StoredFile;
use App\Services\Files\StoredFileDisk;
use App\Services\Files\StoredFileWriter;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

final readonly class EvidenceBundleArchiver
{
    private const string MIME_TYPE = 'application/zip';

    public function __construct(private StoredFileDisk $disk, private StoredFileWriter $writer) {}

    /**
     * @param  list<EvidenceFile>  $evidence
     */
    public function archive(EvidenceBundle $bundle, array $evidence): StoredFile
    {
        $workspace = sys_get_temp_dir().'/moashir-bundle-'.Str::uuid()->toString();

        if (! mkdir($workspace, 0700) && ! is_dir($workspace)) {
            throw new RuntimeException('The bundle workspace could not be created.');
        }

        try {
            $archive = $this->build($workspace, $evidence);

            return $this->writer->storeGeneratedFromPath(new GeneratedFile(
                StoredFilePurpose::EvidenceBundle,
                '',
                $bundle->assessment->request_number.'-evidence.zip',
                'zip',
                self::MIME_TYPE,
            ), $archive, $bundle);
        } finally {
            $this->clean($workspace);
        }
    }

    /**
     * @param  list<EvidenceFile>  $evidence
     */
    private function build(string $workspace, array $evidence): string
    {
        $path = $workspace.'/bundle.zip';
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The evidence bundle could not be created.');
        }

        foreach ($evidence as $index => $file) {
            $local = $workspace.'/'.$index;
            $this->copyTo($file->storedFile, $local);
            $zip->addFile($local, $this->entryNameOf($index, $file));
        }

        if (! $zip->close()) {
            throw new RuntimeException('The evidence bundle could not be written.');
        }

        return $path;
    }

    private function copyTo(StoredFile $file, string $local): void
    {
        $source = $this->disk->readStream($file);
        $target = fopen($local, 'wb');

        try {
            if ($target === false || stream_copy_to_stream($source, $target) === false) {
                throw new RuntimeException('An evidence file could not be copied into the bundle.');
            }
        } finally {
            fclose($source);

            if (is_resource($target)) {
                fclose($target);
            }
        }
    }

    private function entryNameOf(int $index, EvidenceFile $file): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '-', $file->storedFile->original_name) ?? 'file';

        return $file->sub_code.'/'.sprintf('%03d', $index + 1).'-'.$name;
    }

    private function clean(string $workspace): void
    {
        foreach (glob($workspace.'/*') ?: [] as $entry) {
            unlink($entry);
        }

        rmdir($workspace);
    }
}
