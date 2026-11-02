<?php

declare(strict_types=1);

use App\Contracts\Assessments\ReviewedAssessmentState;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\CompletionExpiryOutcome as Outcome;
use App\Enums\Assessments\CompletionRequestStatus;
use App\Enums\Audit\AuditEvent;

it('maps each expiry outcome of the analysis to its request, completion and audit states', function (Outcome $outcome, AssessmentPath $path, ReviewedAssessmentState $status, CompletionRequestStatus $requestStatus, AuditEvent $event, bool $endsAssignment): void {
    expect($outcome->assessmentStatusOn($path))->toBe($status)
        ->and($outcome->requestStatus())->toBe($requestStatus)
        ->and($outcome->auditEvent())->toBe($event)
        ->and($outcome->endsAssignment())->toBe($endsAssignment)
        ->and($path->reviewStages()->needsCompletion->allowsTransitionTo($status))->toBeTrue();
})->with([
    'accredited, continue as submitted' => [Outcome::ContinueAsSubmitted, AssessmentPath::Accredited, AccreditedAssessmentStatus::UnderReview, CompletionRequestStatus::ExpiredContinued, AuditEvent::AssessmentCompletionExpired, false],
    'accredited, decline' => [Outcome::Decline, AssessmentPath::Accredited, AccreditedAssessmentStatus::DeclinedIncomplete, CompletionRequestStatus::ExpiredDeclined, AuditEvent::AssessmentDeclinedIncomplete, true],
    'advanced, continue as submitted' => [Outcome::ContinueAsSubmitted, AssessmentPath::Advanced, AdvancedAssessmentStatus::AwaitingReviewAssignment, CompletionRequestStatus::ExpiredContinued, AuditEvent::AssessmentCompletionExpired, false],
    'advanced, decline' => [Outcome::Decline, AssessmentPath::Advanced, AdvancedAssessmentStatus::DeclinedIncomplete, CompletionRequestStatus::ExpiredDeclined, AuditEvent::AssessmentDeclinedIncomplete, true],
]);

it('uses the exact outcome names shared with the frontend', function (): void {
    expect(array_column(Outcome::cases(), 'value'))->toBe(['continue_as_submitted', 'decline'])
        ->and(array_column(CompletionRequestStatus::cases(), 'value'))->toBe(['open', 'resubmitted', 'cancelled', 'expired_continued', 'expired_declined']);
});
