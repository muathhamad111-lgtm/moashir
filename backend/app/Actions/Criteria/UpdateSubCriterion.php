<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Data\Criteria\SubCriterionAttributes;
use App\Enums\Audit\AuditEvent;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\CriteriaNodeWriter;
use App\Services\Criteria\DraftCriteriaTransaction;
use App\Services\Criteria\WeightAllocation;
use App\Services\Criteria\WeightRebalancer;

final readonly class UpdateSubCriterion
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private CriteriaNodeWriter $writer,
        private WeightAllocation $allocation,
        private WeightRebalancer $rebalancer,
        private CriteriaAuditTrail $audit,
    ) {}

    public function handle(User $actor, SubCriterion $sub, SubCriterionAttributes $attributes): SubCriterion
    {
        return $this->transaction->run($sub->mainCriterion->criteria_version_id, $actor, function () use ($sub, $attributes): SubCriterion {
            $sub->refresh();
            $before = $this->audit->snapshotOf($sub);
            $main = $sub->mainCriterion;
            $weight = $this->allocation->forSub($main, $sub, $attributes->weight, $sub->is_active);

            if ($this->keepsManualPointWeights($sub, $attributes) && ! $main->distribute_equally) {
                $this->allocation->assertSubCoversPoints($sub, $weight);
            }

            $this->writer->fillSub($sub, $attributes->withWeight($weight));
            $sub->save();
            $this->writer->replaceAttachments($sub, $attributes);
            $this->rebalancer->rebalanceSubsOf($main);
            $this->rebalancer->rebalancePointsOf($sub->refresh());

            $this->audit->changed(AuditEvent::CriteriaSubUpdated, $sub, $before);

            return $sub;
        });
    }

    private function keepsManualPointWeights(SubCriterion $sub, SubCriterionAttributes $attributes): bool
    {
        return $sub->scoresGuidingPoints() && ! $attributes->distributeEqually;
    }
}
