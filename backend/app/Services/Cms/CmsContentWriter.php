<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Data\Cms\CmsContentChanges;
use App\Data\Files\ImageChange;
use App\Enums\Files\StoredFilePurpose;
use App\Models\Cms\CmsSection;
use App\Models\Cms\CmsSectionItem;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Files\PublicAssetAttacher;

final readonly class CmsContentWriter
{
    private const string IMAGE_FIELD = 'image_file_id';

    public function __construct(private PublicAssetAttacher $assets) {}

    public function write(CmsSection|CmsSectionItem $owner, CmsContentChanges $changes, User $actor): void
    {
        $image = $this->resolveImage($owner, $changes->image);
        $owner->fill($changes->attributes);
        $owner->forceFill(['updated_by_user_id' => $actor->id]);

        if ($changes->image instanceof ImageChange) {
            $owner->forceFill([self::IMAGE_FIELD => $image?->id]);
        }

        $owner->save();

        if ($image instanceof StoredFile) {
            $this->assets->attach($image, $owner);
        }
    }

    private function resolveImage(CmsSection|CmsSectionItem $owner, ?ImageChange $change): ?StoredFile
    {
        if (! $change instanceof ImageChange || $change->fileId === null) {
            return null;
        }

        return $this->assets->resolve($change->fileId, StoredFilePurpose::CmsImage, $owner, self::IMAGE_FIELD);
    }
}
