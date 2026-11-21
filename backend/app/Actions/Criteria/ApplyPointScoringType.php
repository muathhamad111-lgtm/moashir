<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Enums\Audit\AuditEvent;
use App\Enums\Criteria\ScoringType;
use App\Exceptions\Criteria\CriteriaTreeRejected;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\DraftCriteriaTransaction;

/**
 * «تطبيق على كل المؤشرات»: makes one scoring type the default for a sub-criterion's new indicators and gives it to
 * every indicator it already has, in one step.
 */
final readonly class ApplyPointScoringType
{
    public function __construct(private DraftCriteriaTransaction $transaction, private CriteriaAuditTrail $audit) {}

    public function handle(User $actor, SubCriterion $sub, ScoringType $type): SubCriterion
    {
        return $this->transaction->run($sub->mainCriterion->criteria_version_id, $actor, function () use ($sub, $type): SubCriterion {
            $sub->refresh();

            if (! $sub->scoresGuidingPoints()) {
                throw CriteriaTreeRejected::subEvaluatedDirectly();
            }

            $this->change($sub, ['default_point_scoring_type' => $type], AuditEvent::CriteriaSubUpdated);

            foreach ($sub->guidingPoints()->get() as $point) {
                if ($point->scoring_type !== $type) {
                    $this->change($point, ['scoring_type' => $type], AuditEvent::CriteriaPointUpdated);
                }
            }

            return $sub;
        });
    }

    /**
     * @param  array<string, ScoringType>  $attributes
     */
    private function change(SubCriterion|GuidingPoint $node, array $attributes, AuditEvent $event): void
    {
        $before = $this->audit->snapshotOf($node);
        $node->forceFill($attributes)->save();
        $this->audit->changed($event, $node, $before);
    }
}
