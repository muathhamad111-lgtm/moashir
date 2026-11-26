<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Entities\EntityFreezeGuard;

final readonly class ReviewNotesGate
{
    private const string ATTEMPTED = 'write_review_notes';

    public function __construct(private EntityFreezeGuard $freezeGuard, private AssessmentRowLock $rowLock, private StaffActionReason $reason) {}

    public function enter(User $actor, Assessment $assessment, ?string $reason): Assessment
    {
        $locked = $this->rowLock->lock($assessment->id);
        $this->freezeGuard->assertNotFrozen($locked->entity);
        $this->reason->record($actor, $reason);

        if (! $locked->isOpen()) {
            throw IllegalStateTransition::from((string) $locked->status->value, self::ATTEMPTED);
        }

        return $locked;
    }
}
