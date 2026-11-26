<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Contracts\Entities\EntityErasureStep;
use App\Contracts\Entities\EntityLogoRetention;
use App\Enums\Files\StoredFilePurpose;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Support\ScalarValue;

final readonly class EntityLogoEraser implements EntityErasureStep
{
    public function __construct(private EntityLogoRetention $retention) {}

    public function erase(Entity $entity): void
    {
        $entity->forceFill(['logo_file_id' => null])->save();

        $logoIds = StoredFile::withTrashed()->whereMorphedTo('attachable', $entity)->ofPurpose(StoredFilePurpose::EntityLogo)->pluck('id')->all();
        $releasable = array_values(array_filter(array_map(ScalarValue::int(...), $logoIds), fn (int $id): bool => ! $this->retention->isRetained($id)));

        if ($releasable !== []) {
            StoredFile::withTrashed()->whereKey($releasable)->update(['attachable_type' => null, 'attachable_id' => null]);
        }
    }
}
