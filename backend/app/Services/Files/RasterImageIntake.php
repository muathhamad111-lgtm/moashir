<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Data\Files\GeneratedFile;
use App\Data\Files\ImageUpload;
use App\Exceptions\Files\FileRejected;
use App\Models\Files\StoredFile;
use App\Support\Files\RasterImage;

final readonly class RasterImageIntake
{
    public function __construct(
        private FileIntake $files,
        private ImageReencoder $reencoder,
        private StoredFileWriter $writer,
        private StoredFileRemover $remover,
    ) {}

    public function accept(ImageUpload $upload): StoredFile
    {
        $contents = (string) $upload->file->get();
        $image = $this->acceptableImage($upload, $contents);
        $original = $this->files->accept($upload->file, $upload->purpose, $upload->uploader, $upload->field);
        $reencoded = $this->reencoder->reencode($contents, $image);
        $this->remover->remove($original);

        if ($reencoded === null) {
            throw FileRejected::contentMismatch($upload->field);
        }

        return $this->writer->storeGenerated(new GeneratedFile(
            $upload->purpose,
            $reencoded,
            $upload->fileStem.'.'.$image->extension(),
            $image->extension(),
            $image->mimeType,
        ), $upload->owner);
    }

    private function acceptableImage(ImageUpload $upload, string $contents): RasterImage
    {
        $image = RasterImage::of($contents);
        $mimeType = (string) $upload->file->getMimeType();

        if (! $image instanceof RasterImage || $image->mimeType !== $mimeType || ! $image->hasSidesBetween($upload->sides->minimum, $upload->sides->maximum)) {
            throw FileRejected::contentMismatch($upload->field);
        }

        return $image;
    }
}
