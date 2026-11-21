<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Files\SignedDownloadIssuer;
use Illuminate\Contracts\Auth\Access\Gate;

final readonly class EntityLogoLink
{
    public function __construct(private SignedDownloadIssuer $downloads, private Gate $gate) {}

    public function for(User $viewer, Entity $entity): ?string
    {
        $logo = $entity->loadMissing('logo')->logo;

        if (! $logo instanceof StoredFile || ! $logo->isScannedClean() || $this->gate->forUser($viewer)->denies('viewLogo', $entity)) {
            return null;
        }

        return $this->downloads->issue($logo, $viewer)->url;
    }
}
