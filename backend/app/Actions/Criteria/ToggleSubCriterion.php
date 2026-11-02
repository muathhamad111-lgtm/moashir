<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Enums\Audit\AuditEvent;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\DraftCriteriaTransaction;
use App\Services\Criteria\WeightAllocation;
use App\Services\Criteria\WeightRebalancer;

final readonly class ToggleSubCriterion
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private WeightAllocation $allocation,
        private WeightRebalancer $rebalancer,
        private CriteriaAuditTrail $audit,
    ) {}

    public function handle(User $actor, SubCriterion $sub, bool $isActive): SubCriterion
    {
        return $this->transaction->run($sub->mainCriterion->criteria_version_id, $actor, function () use ($sub, $isActive): SubCriterion {
            $sub->refresh();

            if ($sub->is_active === $isActive) {
                return $sub;
            }

            if ($isActive) {
                $this->allocation->forSub($sub->mainCriterion, $sub, $sub->weight, true);
            }

            $sub->is_active = $isActive;
            $sub->save();
            $this->rebalancer->rebalanceSubsOf($sub->mainCriterion);

            $this->audit->record(AuditEvent::CriteriaSubToggled, $sub, ['active' => ! $isActive], ['active' => $isActive]);

            return $sub->refresh();
        });
    }
}
