<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Enums\Criteria\CriteriaEditKind;
use App\Enums\Criteria\CriteriaEditScope;

final readonly class CriteriaEditEffect
{
    public function __construct(public CriteriaEditKind $kind, public CriteriaEditScope $scope) {}

    public function reachesClosedAssessments(): bool
    {
        return $this->kind->reachesClosedAssessments();
    }

    public function rebindsOpenAssessments(): bool
    {
        return $this->kind === CriteriaEditKind::Structural && $this->scope->rebindsOpenAssessments();
    }

    public function keepsScoresOfUnchangedItems(): bool
    {
        return $this->kind === CriteriaEditKind::TextCorrection || $this->scope->keepsScoresOfUnchangedItems();
    }

    public function clearsAllOpenScores(): bool
    {
        return $this->kind === CriteriaEditKind::Structural && $this->scope->clearsAllOpenScores();
    }

    public function notifiesAffectedEntitiesAndAssessors(): bool
    {
        return $this->kind === CriteriaEditKind::Structural && $this->scope->notifiesAffectedEntitiesAndAssessors();
    }

    /**
     * @return array<string, bool|string>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'scope' => $this->scope->value,
            'reaches_closed_assessments' => $this->reachesClosedAssessments(),
            'rebinds_open_assessments' => $this->rebindsOpenAssessments(),
            'keeps_scores_of_unchanged_items' => $this->keepsScoresOfUnchangedItems(),
            'clears_all_open_scores' => $this->clearsAllOpenScores(),
            'notifies_affected_entities_and_assessors' => $this->notifiesAffectedEntitiesAndAssessors(),
        ];
    }
}
