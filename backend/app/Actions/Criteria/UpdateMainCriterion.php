<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Data\Criteria\MainCriterionAttributes;
use App\Enums\Audit\AuditEvent;
use App\Events\Criteria\MainCriterionWeightChanged;
use App\Models\Criteria\MainCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\CriteriaNodeWriter;
use App\Services\Criteria\DraftCriteriaTransaction;
use App\Services\Criteria\WeightAllocation;
use App\Services\Criteria\WeightRebalancer;

final readonly class UpdateMainCriterion
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private CriteriaNodeWriter $writer,
        private WeightAllocation $allocation,
        private WeightRebalancer $rebalancer,
        private CriteriaAuditTrail $audit,
    ) {}

    public function handle(User $actor, MainCriterion $main, MainCriterionAttributes $attributes): MainCriterion
    {
        return $this->transaction->run($main->criteria_version_id, $actor, function () use ($actor, $main, $attributes): MainCriterion {
            $main->refresh();
            $before = $this->audit->snapshotOf($main);
            $previousWeight = $main->weight;

            if (! $attributes->distributeEqually) {
                $this->allocation->assertMainCoversSubs($main, $attributes->weight);
            }

            $this->writer->fillMain($main, $attributes);
            $main->save();
            $this->rebalancer->rebalanceSubsOf($main);

            $this->audit->changed(AuditEvent::CriteriaMainUpdated, $main, $before);

            if (! $previousWeight->equals($main->weight)) {
                MainCriterionWeightChanged::dispatch($main, $previousWeight, $actor->id);
            }

            return $main;
        });
    }
}
