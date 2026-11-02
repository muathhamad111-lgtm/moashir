<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Enums\Entities\EntityRequestPath;

it('follows the self assessment machine draft, submitted, result available', function (SelfAssessmentStatus $from, SelfAssessmentStatus $to, bool $allowed): void {
    expect($from->allowsTransitionTo($to))->toBe($allowed);
})->with([
    'save a draft' => [SelfAssessmentStatus::Draft, SelfAssessmentStatus::Draft, true],
    'submit a draft' => [SelfAssessmentStatus::Draft, SelfAssessmentStatus::Submitted, true],
    'skip the submission' => [SelfAssessmentStatus::Draft, SelfAssessmentStatus::ResultAvailable, false],
    'compute the result' => [SelfAssessmentStatus::Submitted, SelfAssessmentStatus::ResultAvailable, true],
    'reopen a submission' => [SelfAssessmentStatus::Submitted, SelfAssessmentStatus::Draft, false],
    'reopen a result' => [SelfAssessmentStatus::ResultAvailable, SelfAssessmentStatus::Draft, false],
    'resubmit a result' => [SelfAssessmentStatus::ResultAvailable, SelfAssessmentStatus::Submitted, false],
]);

it('treats only the draft as an open self assessment', function (): void {
    expect(AssessmentPath::SelfAssessment->openStateValues())->toBe(['draft'])
        ->and(AssessmentPath::SelfAssessment->initialState())->toBe(SelfAssessmentStatus::Draft)
        ->and(AssessmentPath::SelfAssessment->stateFrom('result_available'))->toBe(SelfAssessmentStatus::ResultAvailable)
        ->and(AssessmentPath::SelfAssessment->numberPrefix())->toBe('SLF')
        ->and(AssessmentPath::SelfAssessment->requestPath())->toBe(EntityRequestPath::SelfAssessment);
});
