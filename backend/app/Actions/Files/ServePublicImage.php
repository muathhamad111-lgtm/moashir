<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Data\Files\FileDownload;
use App\Enums\Files\FileVisibility;
use App\Enums\Files\StoredFilePurpose;
use App\Models\Files\StoredFile;
use App\Services\Files\StoredFileDisk;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class ServePublicImage
{
    private const array RASTER_TYPES = ['image/png', 'image/jpeg'];

    public function __construct(private StoredFileDisk $disk) {}

    public function handle(string $publicId): FileDownload
    {
        $file = StoredFile::query()
            ->where('public_id', $publicId)
            ->whereIn('purpose', StoredFilePurpose::servedImageValues())
            ->where('visibility', FileVisibility::PublicAsset)
            ->whereIn('mime_type', self::RASTER_TYPES)
            ->clean()
            ->first();

        if (! $file instanceof StoredFile) {
            throw new ModelNotFoundException;
        }

        return new FileDownload($this->disk->readStream($file), 'image.'.($file->extension ?? 'bin'), $file->mime_type, $file->size_bytes);
    }
}
