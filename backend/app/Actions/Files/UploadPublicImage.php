<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Enums\Files\StoredFilePurpose;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Files\PublicImageIntake;
use Illuminate\Http\UploadedFile;

final readonly class UploadPublicImage
{
    public function __construct(private PublicImageIntake $intake) {}

    public function handle(User $uploader, UploadedFile $upload, StoredFilePurpose $purpose): StoredFile
    {
        return $this->intake->accept($upload, $purpose, $uploader);
    }
}
