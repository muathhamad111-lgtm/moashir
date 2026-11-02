<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Enums\Audit\AuditEvent;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\DraftCriteriaTransaction;
use App\Services\Criteria\SiblingOrder;
use App\Services\Criteria\WeightRebalancer;

final readonly class RemoveGuidingPoint
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private SiblingOrder $order,
        private WeightRebalancer $rebalancer,
        private CriteriaAuditTrail $audit,
    ) {}

    public function handle(User $actor, GuidingPoint $point): SubCriterion
    {
        $sub = $point->subCriterion;

        return $this->transaction->run($sub->mainCriterion->criteria_version_id, $actor, function () use ($point, $sub): SubCriterion {
            $point->refresh();
            $before = $this->audit->snapshotOf($point);

            $point->delete();
            $this->order->resequence($sub->guidingPoints()->get());
            $this->rebalancer->rebalancePointsOf($sub->refresh());

            $this->audit->record(AuditEvent::CriteriaPointRemoved, $point, $before, []);

            return $sub;
        });
    }
}
