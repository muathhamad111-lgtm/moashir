<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\CycleOpenBlock;
use App\Data\Award\CycleView;
use App\Enums\Award\AwardCycleStatus;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Award\CycleRecordScope;
use App\Services\Award\QualificationThresholdLock;
use App\Services\Award\RunningCycleGuard;

final readonly class ShowAwardCycle
{
    public function __construct(
        private CycleRecordScope $scope,
        private QualificationThresholdLock $thresholdLock,
        private RunningCycleGuard $running,
    ) {}

    public function handle(User $viewer, AwardCycle $cycle): CycleView
    {
        $revealsStaff = $this->scope->canManage($viewer, $cycle) || $this->scope->readsEveryCycle($viewer);
        $cycle->loadMissing(['regions']);

        if ($revealsStaff) {
            $cycle->load(['members.user', 'initialAssessors.assessor']);
        }

        return new CycleView($cycle, $revealsStaff, $this->scope->canManage($viewer, $cycle), $this->thresholdLock->isLocked($cycle->id), $this->openBlockOf($cycle));
    }

    private function openBlockOf(AwardCycle $cycle): ?CycleOpenBlock
    {
        return $cycle->status === AwardCycleStatus::Draft ? $this->running->blockerFor($cycle, AwardCycleStatus::SubmissionOpen) : null;
    }
}
