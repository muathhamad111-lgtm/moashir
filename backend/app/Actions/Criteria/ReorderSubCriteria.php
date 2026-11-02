<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Enums\Audit\AuditEvent;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\DraftCriteriaTransaction;
use App\Services\Criteria\SiblingOrder;
use Illuminate\Database\Eloquent\Collection;

final readonly class ReorderSubCriteria
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private SiblingOrder $order,
        private CriteriaAuditTrail $audit,
    ) {}

    /**
     * @param  list<int>  $orderedIds
     * @return Collection<int, SubCriterion>
     */
    public function handle(User $actor, MainCriterion $main, array $orderedIds): Collection
    {
        return $this->transaction->run($main->criteria_version_id, $actor, function () use ($main, $orderedIds): Collection {
            $subs = $main->subCriteria()->get();
            $before = $subs->pluck('code')->all();

            $this->order->apply($subs, $orderedIds);
            $after = $main->subCriteria()->get();

            $this->audit->record(AuditEvent::CriteriaSubsReordered, $main, ['order' => $before], ['order' => $after->pluck('code')->all()]);

            return $after;
        });
    }
}
