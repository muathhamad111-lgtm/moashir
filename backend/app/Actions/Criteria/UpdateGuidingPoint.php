<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Data\Criteria\GuidingPointAttributes;
use App\Enums\Audit\AuditEvent;
use App\Models\Criteria\GuidingPoint;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\CriteriaNodeWriter;
use App\Services\Criteria\DraftCriteriaTransaction;
use App\Services\Criteria\WeightAllocation;
use App\Services\Criteria\WeightRebalancer;

final readonly class UpdateGuidingPoint
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private CriteriaNodeWriter $writer,
        private WeightAllocation $allocation,
        private WeightRebalancer $rebalancer,
        private CriteriaAuditTrail $audit,
    ) {}

    public function handle(User $actor, GuidingPoint $point, GuidingPointAttributes $attributes): GuidingPoint
    {
        $sub = $point->subCriterion;

        return $this->transaction->run($sub->mainCriterion->criteria_version_id, $actor, function () use ($point, $sub, $attributes): GuidingPoint {
            $point->refresh();
            $before = $this->audit->snapshotOf($point);
            $weight = $this->allocation->forPoint($sub->refresh(), $point, $attributes->weight, $point->is_active);

            $this->writer->fillPoint($point, $attributes->withWeight($weight));
            $point->save();
            $this->writer->replaceAttachments($point, $attributes);
            $this->rebalancer->rebalancePointsOf($sub);

            $this->audit->changed(AuditEvent::CriteriaPointUpdated, $point->refresh(), $before);

            return $point;
        });
    }
}
