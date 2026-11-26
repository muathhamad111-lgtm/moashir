<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Paths\PathPricing;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use LogicException;

final class PathListing
{
    public function isListed(Entity $entity, PaymentPurpose $path, PathPricing $pricing): bool
    {
        if (! $pricing->isHidden()) {
            return true;
        }

        $assessmentPath = $path->opensPath() ?? throw new LogicException('Only assessment paths are listed on the entity board.');

        return Assessment::query()->ownedBy($entity->id)->ofPath($assessmentPath)->exists()
            || Payment::query()->ownedBy($entity->id)->where('purpose', $path)->open()->exists();
    }
}
