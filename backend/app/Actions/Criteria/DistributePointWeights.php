<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Data\Criteria\WeightAssignment;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Criteria\CriteriaTreeRejected;
use App\Exceptions\Criteria\CriteriaWeightRejected;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\ChildWeightAssigner;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\DraftCriteriaTransaction;

/**
 * Saves the manual weights of a sub-criterion's guiding points in one step.
 */
final readonly class DistributePointWeights
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private ChildWeightAssigner $assigner,
        private CriteriaAuditTrail $audit,
    ) {}

    /**
     * @param  list<WeightAssignment>  $assignments
     */
    public function handle(User $actor, SubCriterion $sub, array $assignments): SubCriterion
    {
        return $this->transaction->run($sub->mainCriterion->criteria_version_id, $actor, function () use ($sub, $assignments): SubCriterion {
            $sub->refresh();

            if (! $sub->scoresGuidingPoints()) {
                throw CriteriaTreeRejected::subEvaluatedDirectly();
            }

            if ($sub->distribute_equally) {
                throw CriteriaWeightRejected::distributedEqually();
            }

            $points = $sub->guidingPoints()->get();

            foreach ($this->assigner->changes($points, $sub->weight, $assignments) as $change) {
                $point = $points->firstOrFail('id', $change->nodeId);
                $before = $this->audit->snapshotOf($point);
                $point->weight = $change->weight;
                $point->save();
                $this->audit->changed(AuditEvent::CriteriaPointUpdated, $point, $before);
            }

            return $sub;
        });
    }
}
