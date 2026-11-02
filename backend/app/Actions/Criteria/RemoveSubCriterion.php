<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Enums\Audit\AuditEvent;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\DraftCriteriaTransaction;
use App\Services\Criteria\SiblingOrder;
use App\Services\Criteria\WeightRebalancer;

final readonly class RemoveSubCriterion
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private SiblingOrder $order,
        private WeightRebalancer $rebalancer,
        private CriteriaAuditTrail $audit,
    ) {}

    public function handle(User $actor, SubCriterion $sub): void
    {
        $main = $sub->mainCriterion;

        $this->transaction->run($main->criteria_version_id, $actor, function () use ($sub, $main): void {
            $sub->refresh();
            $before = $this->audit->snapshotOf($sub);

            $sub->delete();
            $this->order->resequence($main->subCriteria()->get());
            $this->rebalancer->rebalanceSubsOf($main->refresh());

            $this->audit->record(AuditEvent::CriteriaSubRemoved, $sub, $before, []);
        });
    }
}
