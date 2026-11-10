<?php

declare(strict_types=1);

namespace App\Support\Paths;

use App\Data\Paths\AccreditedPathSummary;
use App\Data\Paths\AwardPathSummary;
use App\Data\Paths\EntityPaths;
use App\Data\Paths\SelfPathSummary;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Region;
use App\Models\Finance\Payment;
use App\Support\Assessments\StatusMask;
use App\Support\Certificates\CertificateSummaryPresenter;
use App\Support\Entities\EntityPresenter;
use App\Support\Periods\PeriodPresenter;

final class EntityPathsPresenter
{
    /**
     * @return array{self: array<string, mixed>, accredited: array<string, mixed>, award: array<string, mixed>}
     */
    public static function present(EntityPaths $paths, mixed $viewer): array
    {
        return [
            'self' => self::self($paths->self),
            'accredited' => self::accredited($paths->accredited),
            'award' => self::award($paths->award, $viewer),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function self(SelfPathSummary $self): array
    {
        $draft = $self->openDraft;

        return [
            'pricing' => PathPricingPresenter::present($self->pricing),
            'open_draft' => $draft instanceof Assessment ? [
                'id' => $draft->id,
                'number' => $draft->request_number,
                'status' => (string) $draft->status->value,
                'updated_at' => $draft->updated_at->toIso8601String(),
                'progress' => $self->draftProgress?->toArray(),
            ] : null,
            'last_result' => self::resultOf($self->lastResult),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function accredited(AccreditedPathSummary $accredited): array
    {
        $view = PeriodPresenter::view($accredited->period);

        return [
            'pricing' => PathPricingPresenter::present($accredited->pricing),
            'is_live' => $view['is_live'],
            'cadence' => $view['cadence'],
            'current_period' => $view['current_period'],
            'period_status' => $view['period_status'],
            'next_period' => $view['next_period'],
            'next_submission_from' => $view['next_submission_from'],
            'awaiting_payment' => self::paymentOf($accredited->awaitingPayment),
            'open_request' => self::openRequestOf($accredited),
            'last_result' => self::accreditedResultOf($accredited->lastResult),
            'kit' => KitLinePresenter::placeholder(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function openRequestOf(AccreditedPathSummary $accredited): ?array
    {
        $request = $accredited->openRequest;

        if (! $request instanceof Assessment) {
            return null;
        }

        $needsCompletion = $request->status === AccreditedAssessmentStatus::NeedsCompletion;
        $attribution = PeriodPresenter::attribution($request);

        return [
            'id' => $request->id,
            'number' => $request->request_number,
            'status' => (string) $request->status->value,
            'needs_completion' => $needsCompletion,
            'completion_deadline' => $needsCompletion ? $request->openCompletionRequest?->deadline_at->toIso8601String() : null,
            'remaining_count' => $accredited->openProgress?->remainingCount(),
            'submitted_at' => $request->submitted_at?->toIso8601String(),
            'period_state' => $attribution['period_state'],
            'period_label' => $attribution['period_label'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function accreditedResultOf(?Assessment $result): ?array
    {
        $summary = self::resultOf($result);

        if (! $result instanceof Assessment || $summary === null) {
            return null;
        }

        $certificate = $result->certificate;

        return [
            ...$summary,
            'level' => $certificate?->level?->value,
            ...PeriodPresenter::attribution($result),
            'certificate' => CertificateSummaryPresenter::present($certificate),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function award(AwardPathSummary $award, mixed $viewer): array
    {
        $request = $award->request;

        return [
            'pricing' => PathPricingPresenter::present($award->pricing),
            'window_state' => $award->window->value,
            'cycle' => self::cycleOf($award->cycle),
            'next_cycle' => $award->nextCycle instanceof AwardCycle ? [
                'id' => $award->nextCycle->id,
                'name' => $award->nextCycle->name->toArray(),
                'submission_start' => $award->nextCycle->submission_start->toIso8601String(),
            ] : null,
            'eligibility' => ['eligible' => $award->isEligible(), 'reason' => $award->ineligibility?->value],
            'open_request' => $request instanceof Assessment ? [
                'id' => $request->id,
                'number' => $request->request_number,
                'status' => StatusMask::statusFor($request, $viewer),
                'is_open' => StatusMask::isOpenFor($request, $viewer),
            ] : null,
            'announced' => $award->isAnnounced(),
            'last_result' => $request instanceof Assessment && $request->exposesResult() ? [
                ...(self::resultOf($request) ?? []),
                'announced_at' => $award->cycle?->announced_at?->toIso8601String(),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function cycleOf(?AwardCycle $cycle): ?array
    {
        if (! $cycle instanceof AwardCycle) {
            return null;
        }

        return [
            'id' => $cycle->id,
            'name' => $cycle->name->toArray(),
            'submission_start' => $cycle->submission_start->toIso8601String(),
            'submission_end' => $cycle->submission_end->toIso8601String(),
            'announcement_at' => $cycle->announcement_at?->toIso8601String(),
            'min_entity_age_years' => $cycle->min_entity_age_years,
            'regions' => array_values($cycle->regions->map(static fn (Region $region): array => EntityPresenter::place($region))->all()),
        ];
    }

    /**
     * @return array{assessment_id: int, number: string, percent: string, completed_at: string|null}|null
     */
    private static function resultOf(?Assessment $assessment): ?array
    {
        $snapshot = $assessment?->currentResult;

        if (! $assessment instanceof Assessment || ! $snapshot instanceof AssessmentResultSnapshot) {
            return null;
        }

        return [
            'assessment_id' => $assessment->id,
            'number' => $assessment->request_number,
            'percent' => $snapshot->percent->toDisplay(),
            'completed_at' => $assessment->completed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function paymentOf(?Payment $payment): ?array
    {
        return $payment instanceof Payment ? [
            'payment_id' => $payment->public_id,
            'reference' => $payment->reference,
            'status' => $payment->status->value,
            'channel' => $payment->channel->value,
            'expires_at' => $payment->expires_at?->toIso8601String(),
        ] : null;
    }
}
