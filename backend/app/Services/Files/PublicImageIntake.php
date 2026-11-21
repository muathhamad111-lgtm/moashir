<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Enums\Files\StoredFilePurpose;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use Illuminate\Http\UploadedFile;
use LogicException;

final readonly class PublicImageIntake
{
    public const string FIELD = 'file';

    public function __construct(private FileIntake $intake) {}

    public function accept(UploadedFile $upload, StoredFilePurpose $purpose, User $uploader): StoredFile
    {
        if (! in_array($purpose, [StoredFilePurpose::CmsImage, StoredFilePurpose::KpiImage], true)) {
            throw new LogicException('Only content and KPI images are accepted here.');
        }

        return $this->intake->accept($upload, $purpose, $uploader, self::FIELD);
    }
}
