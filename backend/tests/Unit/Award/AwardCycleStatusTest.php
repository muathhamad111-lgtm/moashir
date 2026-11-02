<?php

declare(strict_types=1);

use App\Enums\Award\AwardCycleStatus as Status;
use App\Exceptions\Award\AwardRejected;
use App\Services\Award\CycleFieldLocks;

it('follows the cycle machine of the analysis', function (Status $from, Status $to, bool $allowed): void {
    expect($from->allowsTransitionTo($to))->toBe($allowed);
})->with([
    'open the submissions' => [Status::Draft, Status::SubmissionOpen, true],
    'close a draft' => [Status::Draft, Status::Closed, false],
    'close the submissions' => [Status::SubmissionOpen, Status::SubmissionClosed, true],
    'reopen the submissions' => [Status::SubmissionClosed, Status::SubmissionOpen, false],
    'close the cycle after the submissions' => [Status::SubmissionClosed, Status::Closed, true],
    'start the committee assessment' => [Status::SubmissionClosed, Status::CommitteeAssessment, true],
    'close after the announcement' => [Status::Announced, Status::Closed, true],
    'reopen a closed cycle without override' => [Status::Closed, Status::Announced, false],
]);

it('tells which cycles run and which can close', function (): void {
    expect(Status::Draft->isRunning())->toBeFalse()
        ->and(Status::Closed->isRunning())->toBeFalse()
        ->and(Status::SubmissionOpen->isRunning())->toBeTrue()
        ->and(Status::SubmissionOpen->isClosable())->toBeFalse()
        ->and(Status::SubmissionClosed->isClosable())->toBeTrue()
        ->and(Status::SubmissionClosed->allowsFilteringDecisions())->toBeTrue()
        ->and(Status::SubmissionOpen->allowsFilteringDecisions())->toBeFalse();
});

it('locks the cycle fields by stage', function (Status $status, string $field, bool $editable): void {
    $check = fn () => app(CycleFieldLocks::class)->assertStageAllows($status, [$field]);

    $editable ? expect($check())->toBeNull() : expect($check)->toThrow(AwardRejected::class, 'cycle.field_locked');
})->with([
    'criteria of a draft' => [Status::Draft, 'criteria_version_id', true],
    'criteria of an open cycle' => [Status::SubmissionOpen, 'criteria_version_id', false],
    'submission start of an open cycle' => [Status::SubmissionOpen, 'submission_start', false],
    'submission end of an open cycle' => [Status::SubmissionOpen, 'submission_end', true],
    'regions after the submissions' => [Status::SubmissionClosed, 'region_ids', false],
    'disqualification score after the submissions' => [Status::SubmissionClosed, 'disqualification_score', false],
    'committee dates after the submissions' => [Status::SubmissionClosed, 'committee_end', true],
    'committee dates during the committee assessment' => [Status::CommitteeAssessment, 'committee_start', false],
    'name of a running cycle' => [Status::CommitteeAssessment, 'name', true],
    'name of a closed cycle' => [Status::Closed, 'name', false],
]);
