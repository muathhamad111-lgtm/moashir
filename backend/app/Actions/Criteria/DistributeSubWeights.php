<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Data\Criteria\WeightAssignment;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Criteria\CriteriaWeightRejected;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\ChildWeightAssigner;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\DraftCriteriaTransaction;
use App\Services\Criteria\WeightAllocation;
use App\Services\Criteria\WeightRebalancer;

/**
 * Saves the manual weights of a main criterion's sub-criteria in one step.
 */
final readonly class DistributeSubWeights
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private ChildWeightAssigner $assigner,
        private WeightAllocation $allocation,
        private WeightRebalancer $rebalancer,
        private CriteriaAuditTrail $audit,
    ) {}

    /**
     * @param  list<WeightAssignment>  $assignments
     */
    public function handle(User $actor, MainCriterion $main, array $assignments): MainCriterion
    {
        return $this->transaction->run($main->criteria_version_id, $actor, function () use ($main, $assignments): MainCriterion {
            $main->refresh();

            if ($main->distribute_equally) {
                throw CriteriaWeightRejected::distributedEqually();
            }

            $subs = $main->subCriteria()->get();

            foreach ($this->assigner->changes($subs, $main->weight, $assignments) as $change) {
                $sub = $subs->firstOrFail('id', $change->nodeId);
                $this->apply($sub, $change);
            }

            return $main;
        });
    }

    private function apply(SubCriterion $sub, WeightAssignment $change): void
    {
        $before = $this->audit->snapshotOf($sub);

        if ($sub->scoresGuidingPoints() && ! $sub->distribute_equally) {
            $this->allocation->assertSubCoversPoints($sub, $change->weight);
        }

        $sub->weight = $change->weight;
        $sub->save();
        $this->rebalancer->rebalancePointsOf($sub);

        $this->audit->changed(AuditEvent::CriteriaSubUpdated, $sub, $before);
    }
}
