<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Files\GeneratedFile;
use App\Enums\Files\StoredFilePurpose;
use App\Exceptions\Files\FileRejected;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Files\FileIntake;
use App\Services\Files\ImageReencoder;
use App\Services\Files\StoredFileRemover;
use App\Services\Files\StoredFileWriter;
use App\Support\Files\RasterImage;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\UploadedFile;

final readonly class EntityLogoIntake
{
    public const string FIELD = 'file';

    public function __construct(
        private FileIntake $files,
        private ImageReencoder $reencoder,
        private StoredFileWriter $writer,
        private StoredFileRemover $remover,
        private Repository $config,
    ) {}

    public function accept(UploadedFile $upload, Entity $entity, User $uploader): StoredFile
    {
        $contents = (string) $upload->get();
        $image = $this->acceptableImage($contents, (string) $upload->getMimeType());
        $original = $this->files->accept($upload, StoredFilePurpose::EntityLogo, $uploader, self::FIELD);
        $reencoded = $this->reencoder->reencode($contents, $image);
        $this->remover->remove($original);

        if ($reencoded === null) {
            throw FileRejected::contentMismatch(self::FIELD);
        }

        return $this->writer->storeGenerated(new GeneratedFile(
            StoredFilePurpose::EntityLogo,
            $reencoded,
            $entity->reference().'-logo.'.$image->extension(),
            $image->extension(),
            $image->mimeType,
        ), $entity);
    }

    private function acceptableImage(string $contents, string $mimeType): RasterImage
    {
        $image = RasterImage::of($contents);
        $minimum = $this->config->integer('moashir.entities.logo.min_side_px');
        $maximum = $this->config->integer('moashir.entities.logo.max_side_px');

        if (! $image instanceof RasterImage || $image->mimeType !== $mimeType || ! $image->hasSidesBetween($minimum, $maximum)) {
            throw FileRejected::contentMismatch(self::FIELD);
        }

        return $image;
    }
}
