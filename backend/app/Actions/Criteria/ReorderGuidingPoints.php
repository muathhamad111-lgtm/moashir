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
use Illuminate\Database\Eloquent\Collection;

final readonly class ReorderGuidingPoints
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private SiblingOrder $order,
        private CriteriaAuditTrail $audit,
    ) {}

    /**
     * @param  list<int>  $orderedIds
     * @return Collection<int, GuidingPoint>
     */
    public function handle(User $actor, SubCriterion $sub, array $orderedIds): Collection
    {
        return $this->transaction->run($sub->mainCriterion->criteria_version_id, $actor, function () use ($sub, $orderedIds): Collection {
            $points = $sub->guidingPoints()->get();
            $before = $points->pluck('code')->all();

            $this->order->apply($points, $orderedIds);
            $after = $sub->guidingPoints()->with('attachmentRequirements')->get();

            $this->audit->record(AuditEvent::CriteriaPointsReordered, $sub, ['order' => $before], ['order' => $after->pluck('code')->all()]);

            return $after;
        });
    }
}
