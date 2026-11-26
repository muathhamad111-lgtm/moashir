<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Files\ImageSideBounds;
use App\Data\Files\ImageUpload;
use App\Enums\Files\StoredFilePurpose;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Files\RasterImageIntake;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\UploadedFile;

final readonly class UserAvatarIntake
{
    public const string FIELD = 'file';

    private const string FILE_STEM = 'avatar';

    public function __construct(private RasterImageIntake $images, private Repository $config) {}

    public function accept(UploadedFile $upload, User $user): StoredFile
    {
        return $this->images->accept(new ImageUpload(
            $upload,
            StoredFilePurpose::UserAvatar,
            self::FIELD,
            new ImageSideBounds($this->config->integer('moashir.users.avatar.min_side_px'), $this->config->integer('moashir.users.avatar.max_side_px')),
            $user,
            $user,
            self::FILE_STEM,
        ));
    }
}
