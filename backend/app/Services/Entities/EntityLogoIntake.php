<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Files\ImageSideBounds;
use App\Data\Files\ImageUpload;
use App\Enums\Files\StoredFilePurpose;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Files\RasterImageIntake;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\UploadedFile;

final readonly class EntityLogoIntake
{
    public const string FIELD = 'file';

    public function __construct(private RasterImageIntake $images, private Repository $config) {}

    public function accept(UploadedFile $upload, Entity $entity, User $uploader): StoredFile
    {
        return $this->images->accept(new ImageUpload(
            $upload,
            StoredFilePurpose::EntityLogo,
            self::FIELD,
            new ImageSideBounds($this->config->integer('moashir.entities.logo.min_side_px'), $this->config->integer('moashir.entities.logo.max_side_px')),
            $entity,
            $uploader,
            $entity->reference().'-logo',
        ));
    }
}
