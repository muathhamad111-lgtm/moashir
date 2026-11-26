<?php

declare(strict_types=1);

use App\Enums\Assessments\AccreditedAssessmentStatus as Status;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Enums\Entities\EntityRequestPath;

it('follows the accredited machine of the analysis', function (Status $from, Status $to, bool $allowed): void {
    expect($from->allowsTransitionTo($to))->toBe($allowed);
})->with([
    'open the draft after payment' => [Status::AwaitingPayment, Status::Draft, true],
    'skip the draft' => [Status::AwaitingPayment, Status::Submitted, false],
    'save the draft' => [Status::Draft, Status::Draft, true],
    'submit the draft' => [Status::Draft, Status::Submitted, true],
    'assign without submission' => [Status::Draft, Status::Assigned, false],
    'assign a submitted request' => [Status::Submitted, Status::Assigned, true],
    'start without assignment' => [Status::Submitted, Status::UnderReview, false],
    'reassign an assigned request' => [Status::Assigned, Status::Assigned, true],
    'start the review' => [Status::Assigned, Status::UnderReview, true],
    'request completion' => [Status::UnderReview, Status::NeedsCompletion, true],
    'resubmit after completion' => [Status::NeedsCompletion, Status::UnderReview, true],
    'complete while waiting for the entity' => [Status::NeedsCompletion, Status::Completed, false],
    'decline at the completion deadline' => [Status::NeedsCompletion, Status::DeclinedIncomplete, true],
    'decline during the review' => [Status::UnderReview, Status::DeclinedIncomplete, false],
    'reopen a declined request' => [Status::DeclinedIncomplete, Status::UnderReview, false],
    'refund a declined request' => [Status::DeclinedIncomplete, Status::Refunded, false],
    'complete the review' => [Status::UnderReview, Status::Completed, true],
    'refund a draft' => [Status::Draft, Status::Refunded, true],
    'refund during the review' => [Status::UnderReview, Status::Refunded, true],
    'refund a completed result' => [Status::Completed, Status::Refunded, false],
    'reopen a completed result' => [Status::Completed, Status::UnderReview, false],
    'reopen a refunded request' => [Status::Refunded, Status::Draft, false],
]);

it('never accepts a state of another path', function (): void {
    expect(Status::Draft->allowsTransitionTo(SelfAssessmentStatus::Submitted))->toBeFalse();
});

it('describes the accredited states used by the queue, the SLA and the entity form', function (): void {
    expect(AssessmentPath::Accredited->openStateValues())->toBe(['awaiting_payment', 'draft', 'submitted', 'assigned', 'under_review', 'needs_completion'])
        ->and(AssessmentPath::Accredited->initialState())->toBe(Status::AwaitingPayment)
        ->and(AssessmentPath::Accredited->numberPrefix())->toBe('ACC')
        ->and(AssessmentPath::Accredited->requestPath())->toBe(EntityRequestPath::Accredited)
        ->and(Status::valuesOf(...Status::queueStates()))->toBe(['submitted', 'assigned', 'under_review', 'needs_completion', 'completed', 'refunded', 'declined_incomplete'])
        ->and(Status::DeclinedIncomplete->isOpen())->toBeFalse()
        ->and(Status::DeclinedIncomplete->exposesResult())->toBeFalse()
        ->and(array_values(array_filter(Status::cases(), static fn (Status $status): bool => $status->runsReviewClock())))->toBe([Status::Submitted, Status::Assigned, Status::UnderReview])
        ->and(array_values(array_filter(Status::cases(), static fn (Status $status): bool => $status->acceptsEntityContent())))->toBe([Status::Draft, Status::NeedsCompletion])
        ->and(Status::forceTargetValues())->toBe(['draft', 'submitted', 'assigned', 'under_review', 'completed'])
        ->and(Status::Completed->exposesResult())->toBeTrue()
        ->and(Status::UnderReview->exposesResult())->toBeFalse();
});

it('keeps the paid drafts in a preparing stage apart from the assignment queue', function (): void {
    expect(AssessmentPath::Accredited->preparingStateValues())->toBe(['draft'])
        ->and(AssessmentPath::Advanced->preparingStateValues())->toBe(['draft'])
        ->and(AssessmentPath::SelfAssessment->preparingStateValues())->toBe([])
        ->and(array_intersect(AssessmentPath::Accredited->preparingStateValues(), AssessmentPath::Accredited->queueStateValues()))->toBe([])
        ->and(Status::Draft->isPreparedByEntity())->toBeTrue()
        ->and(Status::AwaitingPayment->isPreparedByEntity())->toBeFalse()
        ->and(Status::NeedsCompletion->isPreparedByEntity())->toBeFalse();
});
