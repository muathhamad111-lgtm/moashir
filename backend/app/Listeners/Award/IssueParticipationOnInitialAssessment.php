<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Events\Assessments\InitialAssessmentCompleted;
use App\Jobs\Award\IssueParticipationCertificateJob;

final readonly class IssueParticipationOnInitialAssessment
{
    public function handle(InitialAssessmentCompleted $event): void
    {
        if ($event->assessment->isAdvanced()) {
            IssueParticipationCertificateJob::dispatch($event->assessment->id);
        }
    }
}
