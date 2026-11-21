<?php

declare(strict_types=1);

namespace App\Contracts\Insights;

use App\Models\Assessments\Assessment;

/**
 * The excellence tools open for an entity only once the Quick Excellence Kit is activated for the
 * result (operator 2026-11-19). Staff read-only access does not depend on the Kit.
 */
interface KitActivation
{
    public function isActiveFor(Assessment $assessment): bool;

    public function hasActiveKit(int $entityId): bool;
}
