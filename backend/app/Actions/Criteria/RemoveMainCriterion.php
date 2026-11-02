<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Enums\Audit\AuditEvent;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\MainCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\DraftCriteriaTransaction;
use App\Services\Criteria\SiblingOrder;

final readonly class RemoveMainCriterion
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private SiblingOrder $order,
        private CriteriaAuditTrail $audit,
    ) {}

    public function handle(User $actor, MainCriterion $main): void
    {
        $this->transaction->run($main->criteria_version_id, $actor, function (CriteriaVersion $draft) use ($main): void {
            $main->refresh();
            $before = $this->audit->snapshotOf($main);

            $main->delete();
            $this->order->resequence($draft->mainCriteria()->get());

            $this->audit->record(AuditEvent::CriteriaMainRemoved, $main, $before, []);
        });
    }
}
