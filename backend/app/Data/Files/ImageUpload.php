<?php

declare(strict_types=1);

namespace App\Data\Files;

use App\Enums\Files\StoredFilePurpose;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

final readonly class ImageUpload
{
    public function __construct(
        public UploadedFile $file,
        public StoredFilePurpose $purpose,
        public string $field,
        public ImageSideBounds $sides,
        public Model $owner,
        public User $uploader,
        public string $fileStem,
    ) {}
}
