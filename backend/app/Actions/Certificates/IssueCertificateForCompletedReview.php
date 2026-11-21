<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Models\Assessments\Assessment;
use App\Models\Certificates\Certificate;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;

final readonly class IssueCertificateForCompletedReview
{
    public function __construct(private StaffActionReason $reasons, private IssueAccreditedCertificate $issue) {}

    public function handle(User $actor, Assessment $assessment, ?string $reason): Certificate
    {
        $this->reasons->record($actor, $reason);

        return $this->issue->handle($assessment, $actor->id);
    }
}
