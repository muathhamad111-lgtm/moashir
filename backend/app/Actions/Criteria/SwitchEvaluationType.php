<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Enums\Audit\AuditEvent;
use App\Enums\Criteria\EvaluationType;
use App\Events\Criteria\EvaluationTypeSwitched;
use App\Models\Criteria\SubCriterion;
use App\Models\Identity\User;
use App\Services\Criteria\CriteriaAuditTrail;
use App\Services\Criteria\DraftCriteriaTransaction;
use App\Services\Criteria\IndicatorWeights;

/**
 * Switches how a sub-criterion is evaluated. Its indicators always stay (operator, 2026-11-22): evaluated directly
 * they are descriptive and unweighted; scored on indicators again they share the sub-criterion's weight equally.
 */
final readonly class SwitchEvaluationType
{
    public function __construct(
        private DraftCriteriaTransaction $transaction,
        private IndicatorWeights $indicatorWeights,
        private CriteriaAuditTrail $audit,
    ) {}

    public function handle(User $actor, SubCriterion $sub, EvaluationType $type): SubCriterion
    {
        return $this->transaction->run($sub->mainCriterion->criteria_version_id, $actor, function () use ($actor, $sub, $type): SubCriterion {
            $sub->refresh();

            if ($sub->evaluation_type === $type) {
                return $sub;
            }

            $before = $this->stateOf($sub);
            $sub->evaluation_type = $type;
            $sub->save();
            $this->applyIndicatorWeights($sub);

            $this->audit->record(AuditEvent::CriteriaSubTypeSwitched, $sub, $before, $this->stateOf($sub->refresh()));
            EvaluationTypeSwitched::dispatch($sub, $actor->id);

            return $sub;
        });
    }

    private function applyIndicatorWeights(SubCriterion $sub): void
    {
        if ($sub->scoresGuidingPoints()) {
            $this->indicatorWeights->distributeEqually($sub);

            return;
        }

        $this->indicatorWeights->clear($sub);
    }

    /**
     * @return array{evaluation_type: string, distribute_equally: bool, indicator_weights: array<string, string|null>}
     */
    private function stateOf(SubCriterion $sub): array
    {
        $weights = [];

        foreach ($sub->guidingPoints()->get() as $point) {
            $weights[$point->code] = $point->weight?->toDecimal();
        }

        return [
            'evaluation_type' => $sub->evaluation_type->value,
            'distribute_equally' => $sub->distribute_equally,
            'indicator_weights' => $weights,
        ];
    }
}
