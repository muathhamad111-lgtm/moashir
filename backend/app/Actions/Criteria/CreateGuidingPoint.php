<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Data\Criteria\GuidingPointAttributes;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Criteria\CriteriaTreeRejected;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\CriteriaNodeWriter;
use App\Services\Criteria\DraftCriteriaTransaction;
use App\Services\Criteria\WeightAllocation;
use App\Services\Criteria\WeightRebalancer;

final readonly class CreateGuidingPoint
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private CriteriaNodeWriter $writer,
        private WeightAllocation $allocation,
        private WeightRebalancer $rebalancer,
        private CriteriaAuditTrail $audit,
    ) {}

    public function handle(User $actor, SubCriterion $sub, GuidingPointAttributes $attributes, bool $isActive): GuidingPoint
    {
        return $this->transaction->run($sub->mainCriterion->criteria_version_id, $actor, function () use ($sub, $attributes, $isActive): GuidingPoint {
            $sub->refresh();

            if (! $sub->scoresGuidingPoints()) {
                throw CriteriaTreeRejected::subEvaluatedDirectly();
            }

            $weight = $this->allocation->forPoint($sub, null, $attributes->weight, $isActive);
            $point = $this->writer->addPoint($sub, $attributes->withWeight($weight), $isActive);
            $this->rebalancer->rebalancePointsOf($sub);
            $point->refresh();

            $this->audit->created(AuditEvent::CriteriaPointCreated, $point);

            return $point;
        });
    }
}
