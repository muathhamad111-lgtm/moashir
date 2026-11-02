<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\CycleView;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Award\CycleRecordScope;

final readonly class ShowAwardCycle
{
    public function __construct(private CycleRecordScope $scope) {}

    public function handle(User $viewer, AwardCycle $cycle): CycleView
    {
        $revealsStaff = $this->scope->canManage($viewer, $cycle) || $this->scope->readsEveryCycle($viewer);
        $cycle->loadMissing(['regions']);

        if ($revealsStaff) {
            $cycle->load(['members.user', 'initialAssessors.assessor']);
        }

        return new CycleView($cycle, $revealsStaff, $this->scope->canManage($viewer, $cycle));
    }
}
