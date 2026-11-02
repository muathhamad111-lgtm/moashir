<?php

declare(strict_types=1);

use App\Enums\Assessments\AdvancedAssessmentStatus as Status;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\CommitteeAssignmentStatus;
use App\Enums\Award\CommitteeDiscussionMode;
use App\Enums\Award\CommitteeMembershipStatus;
use App\Enums\Award\DiscussionEntryKind;
use App\Enums\Award\RankStage;

it('follows the committee, secretary and results machine of the advanced path', function (Status $from, Status $to, bool $allowed): void {
    expect($from->allowsTransitionTo($to))->toBe($allowed);
})->with([
    'distribute to the committee' => [Status::QualifiedForCommittee, Status::UnderCommitteeAssessment, true],
    'skip the committee' => [Status::QualifiedForCommittee, Status::AwaitingSecretaryApproval, false],
    'last committee assessor submits' => [Status::UnderCommitteeAssessment, Status::AwaitingSecretaryApproval, true],
    'approve during the committee assessment' => [Status::UnderCommitteeAssessment, Status::Approved, false],
    'open a discussion' => [Status::AwaitingSecretaryApproval, Status::CommitteeDiscussion, true],
    'close a discussion' => [Status::CommitteeDiscussion, Status::AwaitingSecretaryApproval, true],
    'return from a discussion' => [Status::CommitteeDiscussion, Status::ReturnedToCommittee, true],
    'approve during a discussion' => [Status::CommitteeDiscussion, Status::Approved, false],
    'return to the committee' => [Status::AwaitingSecretaryApproval, Status::ReturnedToCommittee, true],
    'resume after a return' => [Status::ReturnedToCommittee, Status::UnderCommitteeAssessment, true],
    'resume after a roster change' => [Status::AwaitingSecretaryApproval, Status::UnderCommitteeAssessment, true],
    'approve the average' => [Status::AwaitingSecretaryApproval, Status::Approved, true],
    'return after the approval' => [Status::Approved, Status::ReturnedToCommittee, false],
    'regional winner' => [Status::Approved, Status::RegionalWinner, true],
    'regional non winner' => [Status::Approved, Status::NonWinner, true],
    'qualify for the national stage' => [Status::RegionalWinner, Status::QualifiedForNational, true],
    'national winner' => [Status::QualifiedForNational, Status::NationalWinner, true],
    'national non winner' => [Status::QualifiedForNational, Status::NationalNonWinner, true],
    'national result without the regional stage' => [Status::Approved, Status::NationalWinner, false],
    'withdraw during the committee assessment' => [Status::UnderCommitteeAssessment, Status::Withdrawn, true],
    'withdraw while awaiting the secretary' => [Status::AwaitingSecretaryApproval, Status::Withdrawn, true],
    'withdraw an approved request' => [Status::Approved, Status::Withdrawn, false],
    'reopen a national winner' => [Status::NationalWinner, Status::Approved, false],
]);

it('keeps withdrawal up to the secretary approval and masks every post filtering state', function (): void {
    foreach (Status::committeeStates() as $status) {
        expect($status->isWithdrawable())->toBeTrue()->and($status->isOpen())->toBeTrue();
    }

    foreach (Status::resultStates() as $status) {
        expect($status->isWithdrawable())->toBeFalse()->and($status->isHiddenFromEntityUntilAnnouncement())->toBeTrue();
    }

    expect(Status::NonWinner->isOpen())->toBeFalse()
        ->and(Status::NationalWinner->exposesResult())->toBeTrue()
        ->and(Status::NotQualified->exposesResult())->toBeTrue()
        ->and(Status::Approved->exposesResult())->toBeFalse()
        ->and(Status::QualifiedForNational->isOpen())->toBeTrue()
        ->and(Status::UnderCommitteeAssessment->precedesSecretaryReview())->toBeTrue()
        ->and(Status::CommitteeDiscussion->precedesSecretaryReview())->toBeFalse()
        ->and(Status::valuesOf(...Status::nationalStates()))->toBe(['qualified_for_national', 'national_winner', 'national_non_winner'])
        ->and(Status::Approved->isQualified())->toBeTrue()
        ->and(Status::AwaitingFiltering->isQualified())->toBeFalse();
});

it('moves the cycle between the committee stages and the national approval', function (AwardCycleStatus $from, AwardCycleStatus $to, bool $allowed): void {
    expect($from->allowsTransitionTo($to))->toBe($allowed);
})->with([
    'all requests await the secretary' => [AwardCycleStatus::CommitteeAssessment, AwardCycleStatus::AwaitingSecretary, true],
    'a return reopens the committee stage' => [AwardCycleStatus::AwaitingSecretary, AwardCycleStatus::CommitteeAssessment, true],
    'national approval from the committee stage' => [AwardCycleStatus::CommitteeAssessment, AwardCycleStatus::Approved, true],
    'national approval while awaiting the secretary' => [AwardCycleStatus::AwaitingSecretary, AwardCycleStatus::Approved, true],
    'manual announcement' => [AwardCycleStatus::Approved, AwardCycleStatus::Announced, true],
    'announce before the national approval' => [AwardCycleStatus::AwaitingSecretary, AwardCycleStatus::Announced, false],
    'close without announcing' => [AwardCycleStatus::Approved, AwardCycleStatus::Closed, true],
]);

it('describes the committee membership, assignment, discussion and ranking rules', function (): void {
    expect(CommitteeMembershipStatus::Active->canWork())->toBeTrue()
        ->and(CommitteeMembershipStatus::Disabled->canWork())->toBeFalse()
        ->and(CommitteeMembershipStatus::Substituted->isSeated())->toBeTrue()
        ->and(CommitteeMembershipStatus::Removed->isSeated())->toBeFalse()
        ->and(CommitteeAssignmentStatus::InProgress->acceptsWork())->toBeTrue()
        ->and(CommitteeAssignmentStatus::Completed->acceptsWork())->toBeFalse()
        ->and(CommitteeDiscussionMode::Session->entryKinds())->toBe([DiscussionEntryKind::Opinion])
        ->and(CommitteeDiscussionMode::AgreedOutcome->entryKinds())->toBe([DiscussionEntryKind::Approve, DiscussionEntryKind::RequestEdit])
        ->and(DiscussionEntryKind::Approve->requiresBody())->toBeFalse()
        ->and(DiscussionEntryKind::RequestEdit->requiresBody())->toBeTrue()
        ->and(RankStage::Regional->column())->toBe('regional_rank')
        ->and(RankStage::National->column())->toBe('national_rank')
        ->and(AwardCycleStatus::AwaitingSecretary->isCommitteeStage())->toBeTrue()
        ->and(AwardCycleStatus::Approved->isCommitteeStage())->toBeFalse();
});
