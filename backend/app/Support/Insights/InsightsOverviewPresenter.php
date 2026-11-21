<?php

declare(strict_types=1);

namespace App\Support\Insights;

use App\Data\Insights\InsightsEmptyState;
use App\Data\Insights\InsightsOverview;
use App\Data\Insights\ResultExclusion;
use App\Enums\Insights\InsightsCallToAction;

final class InsightsOverviewPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(InsightsOverview $overview): array
    {
        return [
            'selected' => ResultRefPresenter::presentOptional($overview->selected),
            'exclusions' => array_map(static fn (ResultExclusion $exclusion): array => [
                'assessment_id' => $exclusion->candidate->assessment->id,
                'number' => $exclusion->candidate->assessment->request_number,
                'reason' => $exclusion->reason->value,
            ], $overview->exclusions),
            'empty_state' => self::emptyStateOf($overview->emptyState, $overview->offersActions),
            'in_progress_request' => $overview->inProgressRequestId === null ? null : ['assessment_id' => $overview->inProgressRequestId],
            'rules' => ResultRefPresenter::rules($overview->rules),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function emptyStateOf(?InsightsEmptyState $state, bool $offersActions): ?array
    {
        if (! $state instanceof InsightsEmptyState) {
            return null;
        }

        $action = $offersActions ? $state->reason->callToAction() : null;

        return [
            'reason' => $state->reason->value,
            'cta' => $action instanceof InsightsCallToAction
                ? ['kind' => $action->value, 'assessment_id' => $action === InsightsCallToAction::ViewRequest ? $state->requestId : null]
                : null,
        ];
    }
}
