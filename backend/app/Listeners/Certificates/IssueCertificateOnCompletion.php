<?php

declare(strict_types=1);

namespace App\Listeners\Certificates;

use App\Actions\Certificates\IssueAccreditedCertificate;
use App\Events\Assessments\AssessmentCompleted;
use App\Exceptions\Certificates\CertificateRejected;
use App\Exceptions\Entities\EntityRejected;
use App\Services\Certificates\CertificateSettingsReader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

final class IssueCertificateOnCompletion implements ShouldQueue
{
    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(
        private readonly IssueAccreditedCertificate $issue,
        private readonly CertificateSettingsReader $settings,
    ) {}

    public function shouldQueue(AssessmentCompleted $event): bool
    {
        return $event->certificateRequested && $event->assessment->isAccredited();
    }

    public function handle(AssessmentCompleted $event): void
    {
        if (! $this->settings->accreditedIssuanceEnabled()) {
            return;
        }

        try {
            $this->issue->handle($event->assessment, $event->completedByUserId);
        } catch (CertificateRejected|EntityRejected $rejection) {
            Log::info('certificates.issue_on_completion_skipped', [
                'assessment_id' => $event->assessment->id,
                'code' => $rejection->error()->code,
            ]);
        }
    }
}
