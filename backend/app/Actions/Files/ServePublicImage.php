<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Data\Files\FileDownload;
use App\Enums\Files\FileVisibility;
use App\Enums\Files\StoredFilePurpose;
use App\Models\Files\StoredFile;
use App\Services\Files\StoredFileDisk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class ServePublicImage
{
    private const array RASTER_TYPES = ['image/png', 'image/jpeg'];

    public const string VECTOR_TYPE = 'image/svg+xml';

    public function __construct(private StoredFileDisk $disk) {}

    public function handle(string $publicId): FileDownload
    {
        $file = StoredFile::query()
            ->where('public_id', $publicId)
            ->whereIn('purpose', StoredFilePurpose::servedImageValues())
            ->where('visibility', FileVisibility::PublicAsset)
            ->where(static fn (Builder $type): Builder => $type
                ->whereIn('mime_type', self::RASTER_TYPES)
                ->orWhere(static fn (Builder $vector): Builder => $vector->where('purpose', StoredFilePurpose::CmsLogo)->where('mime_type', self::VECTOR_TYPE)))
            ->clean()
            ->first();

        if (! $file instanceof StoredFile) {
            throw new ModelNotFoundException;
        }

        return new FileDownload($this->disk->readStream($file), 'image.'.($file->extension ?? 'bin'), $file->mime_type, $file->size_bytes);
    }
}
