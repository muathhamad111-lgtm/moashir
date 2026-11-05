<?php

declare(strict_types=1);

namespace App\Enums\Award;

use App\Enums\Identity\AssessorQualification;
use App\Enums\Identity\PermissionKey;

enum CandidateRole: string
{
    case InitialAssessor = 'initial_assessor';
    case FilteringOfficer = 'filtering_officer';
    case AwardSecretary = 'award_secretary';
    case CommitteeAssessor = 'committee_assessor';

    public function permission(): PermissionKey
    {
        return match ($this) {
            self::InitialAssessor => PermissionKey::AssessmentScoreManage,
            self::FilteringOfficer => PermissionKey::AwardFilteringDecide,
            self::AwardSecretary => PermissionKey::AwardSecretaryDecide,
            self::CommitteeAssessor => PermissionKey::AwardCommitteeScore,
        };
    }

    public function qualification(): ?AssessorQualification
    {
        return $this === self::InitialAssessor ? AssessorQualification::AdvancedInitial : null;
    }
}
