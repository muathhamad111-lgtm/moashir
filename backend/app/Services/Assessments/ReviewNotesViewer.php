<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\ReviewNotesView;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;

final readonly class ReviewNotesViewer
{
    public function __construct(private ReviewNoteSheetReader $reader, private AssessmentRecordScope $scope) {}

    public function for(User $viewer, Assessment $assessment): ReviewNotesView
    {
        if (! $viewer->can('readReviewNotes', $assessment)) {
            return ReviewNotesView::hidden($viewer->id);
        }

        return new ReviewNotesView(
            $this->reader->current($assessment),
            $viewer->id,
            $viewer->isSuperAdmin() || $this->scope->accessOf($viewer, $assessment)->revealsStaffIdentities(),
            $assessment->acceptsReviewNotes() && $viewer->can('writeReviewNotes', $assessment),
        );
    }
}
