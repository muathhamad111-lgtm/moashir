<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Actions\Assessments\GetAssessmentResult;
use App\Data\Reports\FeedbackReport;
use App\Enums\Certificates\CertificateStatus;
use App\Models\Assessments\Assessment;
use App\Models\Certificates\Certificate;
use App\Services\Assessments\AssessmentSummaryReader;

final readonly class GetFeedbackReport
{
    public function __construct(private GetAssessmentResult $results, private AssessmentSummaryReader $summaries) {}

    public function handle(Assessment $assessment): FeedbackReport
    {
        $result = $this->results->handle($assessment->loadMissing(['entity.region', 'entity.city']));

        if (! $result->isAvailable()) {
            return new FeedbackReport($result);
        }

        return new FeedbackReport($result, Certificate::query()
            ->where('assessment_id', $assessment->id)
            ->where('status', CertificateStatus::Issued)
            ->first(), $this->summaries->currentOf($assessment));
    }
}
