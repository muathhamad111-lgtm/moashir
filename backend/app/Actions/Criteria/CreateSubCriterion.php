<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Data\Criteria\SubCriterionAttributes;
use App\Enums\Audit\AuditEvent;
use App\Events\Criteria\SubCriterionCreated;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\CriteriaNodeWriter;
use App\Services\Criteria\DraftCriteriaTransaction;
use App\Services\Criteria\WeightAllocation;
use App\Services\Criteria\WeightRebalancer;

final readonly class CreateSubCriterion
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private CriteriaNodeWriter $writer,
        private WeightAllocation $allocation,
        private WeightRebalancer $rebalancer,
        private CriteriaAuditTrail $audit,
    ) {}

    public function handle(User $actor, MainCriterion $main, SubCriterionAttributes $attributes, bool $isActive): SubCriterion
    {
        return $this->transaction->run($main->criteria_version_id, $actor, function () use ($actor, $main, $attributes, $isActive): SubCriterion {
            $main->refresh();
            $weight = $this->allocation->forSub($main, null, $attributes->weight, $isActive);

            $sub = $this->writer->addSub($main, $attributes->withWeight($weight), $isActive);
            $this->rebalancer->rebalanceSubsOf($main);
            $sub->refresh();

            $this->audit->created(AuditEvent::CriteriaSubCreated, $sub);
            SubCriterionCreated::dispatch($sub, $actor->id);

            return $sub;
        });
    }
}
