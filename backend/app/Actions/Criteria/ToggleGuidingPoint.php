<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Enums\Audit\AuditEvent;
use App\Models\Criteria\GuidingPoint;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\DraftCriteriaTransaction;
use App\Services\Criteria\WeightAllocation;
use App\Services\Criteria\WeightRebalancer;

final readonly class ToggleGuidingPoint
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private WeightAllocation $allocation,
        private WeightRebalancer $rebalancer,
        private CriteriaAuditTrail $audit,
    ) {}

    public function handle(User $actor, GuidingPoint $point, bool $isActive): GuidingPoint
    {
        $sub = $point->subCriterion;

        return $this->transaction->run($sub->mainCriterion->criteria_version_id, $actor, function () use ($point, $sub, $isActive): GuidingPoint {
            $point->refresh();

            if ($point->is_active === $isActive) {
                return $point;
            }

            if ($isActive) {
                $this->allocation->forPoint($sub->refresh(), $point, $point->weight, true);
            }

            $point->is_active = $isActive;
            $point->save();
            $this->rebalancer->rebalancePointsOf($sub);

            $this->audit->record(AuditEvent::CriteriaPointToggled, $point, ['active' => ! $isActive], ['active' => $isActive]);

            return $point->refresh();
        });
    }
}
