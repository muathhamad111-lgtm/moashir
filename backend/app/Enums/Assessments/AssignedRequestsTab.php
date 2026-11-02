<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum AssignedRequestsTab: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    /**
     * @return list<string>
     */
    public function statusValues(): array
    {
        return array_values(array_unique([
            ...AccreditedAssessmentStatus::valuesOf(...match ($this) {
                self::New => [AccreditedAssessmentStatus::Assigned],
                self::InProgress => [AccreditedAssessmentStatus::UnderReview, AccreditedAssessmentStatus::NeedsCompletion],
                self::Completed => [AccreditedAssessmentStatus::Completed],
            }),
            ...AdvancedAssessmentStatus::valuesOf(...match ($this) {
                self::New => [AdvancedAssessmentStatus::Assigned],
                self::InProgress => [AdvancedAssessmentStatus::UnderInitialAssessment],
                self::Completed => [AdvancedAssessmentStatus::AwaitingFiltering, AdvancedAssessmentStatus::QualifiedForCommittee, AdvancedAssessmentStatus::NotQualified],
            }),
        ]));
    }
}
