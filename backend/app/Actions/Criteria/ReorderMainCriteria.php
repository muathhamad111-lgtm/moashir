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
use Illuminate\Database\Eloquent\Collection;

final readonly class ReorderMainCriteria
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private SiblingOrder $order,
        private CriteriaAuditTrail $audit,
    ) {}

    /**
     * @param  list<int>  $orderedIds
     * @return Collection<int, MainCriterion>
     */
    public function handle(User $actor, array $orderedIds): Collection
    {
        return $this->transaction->runOnCurrentDraft($actor, function (CriteriaVersion $draft) use ($orderedIds): Collection {
            $mains = $draft->mainCriteria()->get();
            $before = $mains->pluck('code')->all();

            $this->order->apply($mains, $orderedIds);
            $after = $draft->mainCriteria()->with(['subCriteria'])->get();

            $this->audit->record(AuditEvent::CriteriaMainsReordered, $draft, ['order' => $before], ['order' => $after->pluck('code')->all()]);

            return $after;
        });
    }
}
