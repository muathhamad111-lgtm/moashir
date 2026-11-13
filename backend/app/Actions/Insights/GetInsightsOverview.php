<?php

declare(strict_types=1);

namespace App\Actions\Insights;

use App\Data\Insights\InsightsEmptyState;
use App\Data\Insights\InsightsOverview;
use App\Data\Insights\ResultCandidate;
use App\Data\Insights\ResultExclusion;
use App\Enums\Insights\InsightsEmptyReason;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Insights\EntityAssessmentActivity;
use App\Services\Insights\InsightsRulesReader;
use App\Services\Insights\VisibleAccreditedResults;
use App\Support\Insights\EligibleResultSelector;

final readonly class GetInsightsOverview
{
    public function __construct(
        private VisibleAccreditedResults $results,
        private EligibleResultSelector $selector,
        private EntityAssessmentActivity $activity,
        private InsightsRulesReader $rules,
    ) {}

    public function handle(User $viewer, Entity $entity): InsightsOverview
    {
        $candidates = $this->results->of($viewer, $entity);
        $selected = $this->selector->latest($candidates);
        $exclusions = $this->selector->exclusionsNewerThan($selected, $candidates);
        $openRequestId = $this->results->coversAccredited($viewer) ? $this->activity->openAccreditedRequestId($entity->id) : null;

        return new InsightsOverview(
            $selected,
            $exclusions,
            $selected instanceof ResultCandidate ? null : $this->emptyStateOf($entity, $exclusions, $openRequestId),
            $openRequestId,
            $this->rules->read(),
            $viewer->entity_id === $entity->id,
        );
    }

    /**
     * @param  list<ResultExclusion>  $exclusions
     */
    private function emptyStateOf(Entity $entity, array $exclusions, ?int $openRequestId): InsightsEmptyState
    {
        return match (true) {
            $exclusions !== [] => new InsightsEmptyState(InsightsEmptyReason::UnderReview),
            $openRequestId !== null => new InsightsEmptyState(InsightsEmptyReason::InProgress, $openRequestId),
            $this->activity->hasSelfResult($entity->id) => new InsightsEmptyState(InsightsEmptyReason::OnlySelf),
            default => new InsightsEmptyState(InsightsEmptyReason::NoVerifiedResult),
        };
    }
}
