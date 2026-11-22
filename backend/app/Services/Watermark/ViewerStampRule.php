<?php

declare(strict_types=1);

namespace App\Services\Watermark;

use App\Enums\Files\StoredFilePurpose;
use App\Models\Identity\User;

/**
 * Who receives stamped files: the staff accounts that work on an assessment (assessors, reviewers and the rest).
 * Entity accounts open their own evidence as uploaded (operator decision, 2026-11-22).
 */
final readonly class ViewerStampRule
{
    /**
     * The file itself is served as a copy stamped for this viewer.
     */
    public function servesStampedCopy(User $viewer, StoredFilePurpose $purpose): bool
    {
        return $purpose->isWatermarked() && $this->stampsFor($viewer);
    }

    /**
     * The link leads to stamped content: a stamped copy, or an evidence bundle whose files are stamped.
     */
    public function issuesStampedLink(User $viewer, StoredFilePurpose $purpose): bool
    {
        return $purpose->isStampedForViewer() && $this->stampsFor($viewer);
    }

    private function stampsFor(User $viewer): bool
    {
        return ! $viewer->isEntityUser();
    }
}
