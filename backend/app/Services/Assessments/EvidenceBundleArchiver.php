<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Files\GeneratedFile;
use App\Data\Watermark\StampedFile;
use App\Enums\Files\AccessIntent;
use App\Enums\Files\StoredFilePurpose;
use App\Models\Assessments\EvidenceBundle;
use App\Models\Assessments\EvidenceFile;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Files\StoredFileWriter;
use App\Services\Watermark\FileStamper;
use App\Services\Watermark\WatermarkWorkspace;
use App\Services\Watermark\WatermarkWorkspaces;
use RuntimeException;
use ZipArchive;

final readonly class EvidenceBundleArchiver
{
    private const string MIME_TYPE = 'application/zip';

    public function __construct(
        private StoredFileWriter $writer,
        private FileStamper $stamper,
        private WatermarkWorkspaces $workspaces,
    ) {}

    /**
     * @param  list<EvidenceFile>  $evidence
     */
    public function archive(EvidenceBundle $bundle, array $evidence): StoredFile
    {
        $requester = User::withTrashed()->findOrFail($bundle->requested_by_user_id);
        $workspace = $this->workspaces->open();

        try {
            $archive = $this->build($workspace, $requester, $evidence);

            return $this->writer->storeGeneratedFromPath(new GeneratedFile(
                StoredFilePurpose::EvidenceBundle,
                '',
                $bundle->assessment->request_number.'-evidence.zip',
                'zip',
                self::MIME_TYPE,
            ), $archive, $bundle);
        } finally {
            $workspace->close();
        }
    }

    /**
     * @param  list<EvidenceFile>  $evidence
     */
    private function build(WatermarkWorkspace $workspace, User $requester, array $evidence): string
    {
        $path = $workspace->path('bundle.zip');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The evidence bundle could not be created.');
        }

        foreach ($evidence as $index => $file) {
            $local = $workspace->path((string) $index);
            $stamped = $this->stampInto($requester, $file->storedFile, $local);
            $zip->addFile($local, $this->entryNameOf($index, $file, $stamped->fileName));
        }

        if (! $zip->close()) {
            throw new RuntimeException('The evidence bundle could not be written.');
        }

        return $path;
    }

    private function stampInto(User $requester, StoredFile $file, string $local): StampedFile
    {
        $workspace = $this->workspaces->open();

        try {
            $stamped = $this->stamper->stamp($requester, $file, AccessIntent::Download, $workspace);

            if (! copy($stamped->path, $local)) {
                throw new RuntimeException('A stamped evidence file could not be added to the bundle.');
            }

            return $stamped;
        } finally {
            $workspace->close();
        }
    }

    private function entryNameOf(int $index, EvidenceFile $file, string $fileName): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '-', $fileName) ?? 'file';

        return $file->ownerCode().'/'.sprintf('%03d', $index + 1).'-'.$name;
    }
}
