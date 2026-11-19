<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Contracts\Insights\KitActivation;
use App\Models\Assessments\Assessment;

/**
 * The Quick Excellence Kit has no backend yet, so no result has an active Kit and the entity's
 * excellence tools stay locked («قريباً»). Replace this binding when the Kit API is built.
 */
final readonly class UnbuiltKitActivation implements KitActivation
{
    public function isActiveFor(Assessment $assessment): bool
    {
        return false;
    }

    public function hasActiveKit(int $entityId): bool
    {
        return false;
    }
}
