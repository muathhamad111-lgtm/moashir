<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Paths\EntityPaths;
use App\Data\Paths\NextStep;
use App\Data\Paths\NextStepTarget;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Award\AwardWindowState;
use App\Enums\Dashboards\NextStepKey;
use App\Models\Assessments\Assessment;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Support\Assessments\StatusMask;

final class NextStepResolver
{
    public function resolve(EntityPaths $paths, User $viewer): ?NextStep
    {
        return $this->settlePayment($paths)
            ?? $this->accreditedStep($paths, $viewer, AccreditedAssessmentStatus::NeedsCompletion, NextStepKey::CompleteRequest)
            ?? $this->awardStep($paths, $viewer, AdvancedAssessmentStatus::NeedsCompletion, NextStepKey::CompleteRequest)
            ?? $this->accreditedStep($paths, $viewer, AccreditedAssessmentStatus::Draft, NextStepKey::FinishDraft)
            ?? $this->awardDraft($paths, $viewer)
            ?? $this->paidSelfDraft($paths, $viewer)
            ?? $this->trackReview($paths, $viewer)
            ?? $this->applyAccredited($paths)
            ?? $this->applyAward($paths)
            ?? $this->selfStep($paths, $viewer);
    }

    private function settlePayment(EntityPaths $paths): ?NextStep
    {
        $payment = $paths->paymentToSettle;

        if (! $payment instanceof Payment) {
            return null;
        }

        $path = $payment->purpose->opensPath() ?? AssessmentPath::Accredited;

        return new NextStep(NextStepKey::SettlePayment, $path, NextStepTarget::ofPath($path), [
            ...$this->emptyContext(),
            'payment_id' => $payment->public_id,
            'cycle_id' => $payment->award_cycle_id,
            'payment_reference' => $payment->reference,
            'payment_channel' => $payment->channel->value,
            'payment_status' => $payment->status->value,
            'payment_expires_at' => $payment->expires_at?->toIso8601String(),
        ]);
    }

    private function paidSelfDraft(EntityPaths $paths, User $viewer): ?NextStep
    {
        $self = $paths->self;

        if (! $self->openDraft instanceof Assessment || ! $self->hasPaidDraft()) {
            return null;
        }

        return new NextStep(NextStepKey::FinishDraft, AssessmentPath::SelfAssessment, new NextStepTarget(NextStepTarget::SELF), $this->requestContext($self->openDraft, $viewer));
    }

    private function accreditedStep(EntityPaths $paths, User $viewer, AccreditedAssessmentStatus $status, NextStepKey $key): ?NextStep
    {
        $request = $paths->accredited->openRequest;

        if (! $request instanceof Assessment || $request->status !== $status) {
            return null;
        }

        return new NextStep($key, AssessmentPath::Accredited, NextStepTarget::accreditedRequest($request->id), [
            ...$this->requestContext($request, $viewer),
            'deadline_at' => $request->openCompletionRequest?->deadline_at->toIso8601String(),
            'period_ends_on' => $paths->accredited->period->current?->ends_on->toDateString(),
        ]);
    }

    private function awardStep(EntityPaths $paths, User $viewer, AdvancedAssessmentStatus $status, NextStepKey $key): ?NextStep
    {
        $request = $paths->award->request;

        if (! $request instanceof Assessment || $request->status !== $status) {
            return null;
        }

        return new NextStep($key, AssessmentPath::Advanced, new NextStepTarget(NextStepTarget::AWARD), [
            ...$this->requestContext($request, $viewer),
            'deadline_at' => $request->openCompletionRequest?->deadline_at->toIso8601String(),
            'cycle_id' => $request->award_cycle_id,
        ]);
    }

    private function awardDraft(EntityPaths $paths, User $viewer): ?NextStep
    {
        if ($paths->award->window !== AwardWindowState::Open) {
            return null;
        }

        $step = $this->awardStep($paths, $viewer, AdvancedAssessmentStatus::Draft, NextStepKey::FinishDraft);

        return $step instanceof NextStep ? new NextStep($step->key, $step->path, $step->target, [
            ...$step->context,
            'deadline_at' => $paths->award->cycle?->submission_end->toIso8601String(),
        ]) : null;
    }

    private function trackReview(EntityPaths $paths, User $viewer): ?NextStep
    {
        $accredited = $paths->accredited->openRequest;

        if ($accredited instanceof Assessment && $accredited->status instanceof AccreditedAssessmentStatus && $accredited->status->awaitsResult()) {
            return new NextStep(NextStepKey::TrackReview, AssessmentPath::Accredited, NextStepTarget::accreditedRequest($accredited->id), [
                ...$this->requestContext($accredited, $viewer),
                'period_ends_on' => $paths->accredited->period->current?->ends_on->toDateString(),
            ]);
        }

        $award = $paths->award->request;

        if ($award instanceof Assessment && StatusMask::isOpenFor($award, $viewer) && $award->status !== AdvancedAssessmentStatus::Draft) {
            return new NextStep(NextStepKey::TrackReview, AssessmentPath::Advanced, new NextStepTarget(NextStepTarget::AWARD), [
                ...$this->requestContext($award, $viewer),
                'cycle_id' => $award->award_cycle_id,
            ]);
        }

        return null;
    }

    private function applyAccredited(EntityPaths $paths): ?NextStep
    {
        $accredited = $paths->accredited;
        $state = $accredited->period->stateValue();

        if (! $accredited->period->isLive() || $state === null || ! $state->invitesSubmission() || ! $accredited->apply->canStart()) {
            return null;
        }

        return new NextStep(NextStepKey::ApplyAccredited, AssessmentPath::Accredited, new NextStepTarget(NextStepTarget::ACCREDITED), [
            ...$this->emptyContext(),
            'period_ends_on' => $accredited->period->current?->ends_on->toDateString(),
        ]);
    }

    private function applyAward(EntityPaths $paths): ?NextStep
    {
        $award = $paths->award;

        if ($award->window !== AwardWindowState::Open || ! $award->isEligible()) {
            return null;
        }

        return new NextStep(NextStepKey::ApplyAward, AssessmentPath::Advanced, new NextStepTarget(NextStepTarget::AWARD), [
            ...$this->emptyContext(),
            'deadline_at' => $award->cycle?->submission_end->toIso8601String(),
            'cycle_id' => $award->cycle?->id,
        ]);
    }

    private function selfStep(EntityPaths $paths, User $viewer): ?NextStep
    {
        $self = $paths->self;

        if ($self->openDraft instanceof Assessment) {
            return new NextStep(NextStepKey::ContinueSelfDraft, AssessmentPath::SelfAssessment, new NextStepTarget(NextStepTarget::SELF), $this->requestContext($self->openDraft, $viewer));
        }

        return $self->pricing->isHidden() || $self->awaitingPayment instanceof Payment ? null : new NextStep(NextStepKey::StartSelf, AssessmentPath::SelfAssessment, new NextStepTarget(NextStepTarget::SELF), $this->emptyContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function requestContext(Assessment $request, User $viewer): array
    {
        return [
            ...$this->emptyContext(),
            'assessment_id' => $request->id,
            'number' => $request->request_number,
            'status' => StatusMask::statusFor($request, $viewer),
            'is_paid' => $request->payment_id !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyContext(): array
    {
        return [
            'assessment_id' => null,
            'number' => null,
            'status' => null,
            'payment_id' => null,
            'is_paid' => null,
            'deadline_at' => null,
            'period_ends_on' => null,
            'cycle_id' => null,
            'payment_reference' => null,
            'payment_channel' => null,
            'payment_status' => null,
            'payment_expires_at' => null,
        ];
    }
}
