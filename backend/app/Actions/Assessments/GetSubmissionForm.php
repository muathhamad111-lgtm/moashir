<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\SubmissionForm;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\CriteriaMoveSummaryReader;
use App\Services\Assessments\SubmissionContentReader;
use App\Services\Assessments\SubmissionGapsReader;
use App\Services\Assessments\UploadLimitsReader;
use App\Services\Criteria\CriteriaTreeSerializer;

final readonly class GetSubmissionForm
{
    public function __construct(
        private CriteriaTreeSerializer $serializer,
        private SubmissionContentReader $content,
        private SubmissionGapsReader $gaps,
        private UploadLimitsReader $limits,
        private CriteriaMoveSummaryReader $moves,
    ) {}

    public function handle(User $viewer, Assessment $assessment): SubmissionForm
    {
        $content = $assessment->isOwnedBy($viewer->entity_id) ? $this->content->forOwner($assessment) : $this->content->forReviewer($assessment);

        return new SubmissionForm(
            $content->cv,
            $this->serializer->serialize($assessment->criteriaVersion),
            $content->evidence,
            $this->gaps->read($assessment, $content->subs),
            $assessment->loadMissing('openCompletionRequest')->openCompletionRequest,
            $this->limits->read($assessment->path),
            $this->moves->latestFor($assessment),
        );
    }
}
