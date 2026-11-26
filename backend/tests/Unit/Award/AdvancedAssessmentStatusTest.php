<?php

declare(strict_types=1);

use App\Data\Assessments\ReviewStages;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AdvancedAssessmentStatus as Status;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Entities\EntityRequestPath;

it('follows the advanced machine of the analysis up to filtering', function (Status $from, Status $to, bool $allowed): void {
    expect($from->allowsTransitionTo($to))->toBe($allowed);
})->with([
    'save the draft' => [Status::Draft, Status::Draft, true],
    'submit the draft' => [Status::Draft, Status::AwaitingReviewAssignment, true],
    'expire the draft at the submission end' => [Status::Draft, Status::Expired, true],
    'assign a draft' => [Status::Draft, Status::Assigned, false],
    'return for completion' => [Status::AwaitingReviewAssignment, Status::NeedsCompletion, true],
    'resubmit after completion' => [Status::NeedsCompletion, Status::AwaitingReviewAssignment, true],
    'decline at the completion deadline' => [Status::NeedsCompletion, Status::DeclinedIncomplete, true],
    'resume to the assessor who had not started' => [Status::NeedsCompletion, Status::Assigned, true],
    'resume to the initial assessment' => [Status::NeedsCompletion, Status::UnderInitialAssessment, true],
    'complete the initial assessment while waiting for the entity' => [Status::NeedsCompletion, Status::AwaitingFiltering, false],
    'assign the initial assessor' => [Status::AwaitingReviewAssignment, Status::Assigned, true],
    'return after the assignment' => [Status::Assigned, Status::NeedsCompletion, true],
    'return during the initial assessment' => [Status::UnderInitialAssessment, Status::NeedsCompletion, true],
    'return after the initial assessment' => [Status::AwaitingFiltering, Status::NeedsCompletion, false],
    'start the initial assessment' => [Status::Assigned, Status::UnderInitialAssessment, true],
    'complete the initial assessment' => [Status::UnderInitialAssessment, Status::AwaitingFiltering, true],
    'skip the initial assessment' => [Status::Assigned, Status::AwaitingFiltering, false],
    'qualify' => [Status::AwaitingFiltering, Status::QualifiedForCommittee, true],
    'disqualify' => [Status::AwaitingFiltering, Status::NotQualified, true],
    'qualify during the initial assessment' => [Status::UnderInitialAssessment, Status::QualifiedForCommittee, false],
    'reverse a disqualification' => [Status::NotQualified, Status::QualifiedForCommittee, false],
    'withdraw a submitted request' => [Status::AwaitingReviewAssignment, Status::Withdrawn, true],
    'withdraw during the initial assessment' => [Status::UnderInitialAssessment, Status::Withdrawn, true],
    'withdraw a qualified request' => [Status::QualifiedForCommittee, Status::Withdrawn, true],
    'withdraw a draft' => [Status::Draft, Status::Withdrawn, false],
    'reopen a withdrawn request' => [Status::Withdrawn, Status::AwaitingReviewAssignment, false],
    'submit after a conditions rejection' => [Status::RejectedConditions, Status::AwaitingReviewAssignment, false],
    'submit an expired draft' => [Status::Expired, Status::AwaitingReviewAssignment, false],
]);

it('never accepts a state of another path', function (): void {
    expect(Status::Draft->allowsTransitionTo(AccreditedAssessmentStatus::Submitted))->toBeFalse();
});

it('marks the terminal, queued, withdrawable and filtering states', function (): void {
    expect(AssessmentPath::Advanced->openStateValues())->toBe([
        'draft', 'awaiting_review_assignment', 'needs_completion', 'assigned', 'under_initial_assessment', 'awaiting_filtering',
        'qualified_for_committee', 'under_committee_assessment', 'committee_discussion', 'awaiting_secretary_approval',
        'returned_to_committee', 'approved', 'regional_winner', 'qualified_for_national',
    ])
        ->and(AssessmentPath::Advanced->queueStateValues())->not->toContain('draft')->not->toContain('rejected_conditions')->not->toContain('expired')
        ->and(Status::QualifiedForCommittee->isWithdrawable())->toBeTrue()
        ->and(Status::NotQualified->isWithdrawable())->toBeFalse()
        ->and(Status::Draft->isWithdrawable())->toBeFalse()
        ->and(Status::valuesOf(...Status::filteringStates()))->toBe(['awaiting_filtering', 'qualified_for_committee', 'not_qualified'])
        ->and(Status::UnderInitialAssessment->isHiddenFromEntityUntilAnnouncement())->toBeFalse()
        ->and(Status::NotQualified->isHiddenFromEntityUntilAnnouncement())->toBeTrue()
        ->and(Status::AwaitingFiltering->exposesResult())->toBeFalse()
        ->and(array_values(array_filter(Status::cases(), static fn (Status $status): bool => $status->acceptsCompletionReturn())))
        ->toBe([Status::AwaitingReviewAssignment, Status::Assigned, Status::UnderInitialAssessment])
        ->and(Status::NeedsCompletion->hasAssessor())->toBeFalse()
        ->and(Status::NeedsCompletion->runsReviewClock())->toBeFalse();
});

it('maps the advanced path to its review stages, number prefix and request path', function (): void {
    $stages = AssessmentPath::Advanced->reviewStages();

    expect($stages->awaitingAssignment)->toBe(Status::AwaitingReviewAssignment)
        ->and($stages->underReview)->toBe(Status::UnderInitialAssessment)
        ->and($stages->reviewed)->toBe(Status::AwaitingFiltering)
        ->and($stages->afterCompletion)->toBe(Status::AwaitingReviewAssignment)
        ->and(AssessmentPath::Advanced->numberPrefix())->toBe('ADV')
        ->and(AssessmentPath::Advanced->requestPath())->toBe(EntityRequestPath::Advanced)
        ->and(AssessmentPath::Advanced->followsCriteriaEdits())->toBeFalse()
        ->and(AssessmentPath::Accredited->followsCriteriaEdits())->toBeTrue()
        ->and(AssessmentPath::Advanced->reviewClockStateValues())->toBe(['assigned', 'under_initial_assessment']);
    expect(fn (): ReviewStages => AssessmentPath::SelfAssessment->reviewStages())->toThrow(LogicException::class);
});
