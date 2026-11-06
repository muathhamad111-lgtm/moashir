<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Certificates\IssueAccreditedCertificate;
use App\Actions\Certificates\RevokeCertificate;
use App\Contracts\Demo\DemoAccreditedRequestSource;
use App\Data\Demo\DemoAccreditedResult;
use App\Data\Demo\DemoInsightsCast;
use App\Enums\Assessments\FeedbackKind;
use App\Enums\Platform\DemoCertificateOutcome;
use App\Enums\Platform\DemoRecordKind;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentFeedbackItem;
use App\Models\Assessments\AssessmentSummary;
use App\Models\Certificates\Certificate;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Support\RequestContext;

final readonly class DemoAccreditedResultInstaller
{
    private const array FEEDBACK = [
        FeedbackKind::Strength->value => 'توجد سياسة معتمدة ومحدّثة مع شواهد موثقة على تطبيقها (بيانات تجريبية).',
        FeedbackKind::Improvement->value => 'يُستحسن ربط الإجراءات بمؤشرات أداء دورية وتوثيق نتائج مراجعتها (بيانات تجريبية).',
    ];

    private const int FEEDBACK_SUBS = 4;

    private const string REVOKE_REASON = 'إلغاء تجريبي لعرض حالة الشهادة الملغاة في أدوات التميّز (بيانات تجريبية).';

    public function __construct(
        private DemoResultScorer $scorer,
        private IssueAccreditedCertificate $certificates,
        private RevokeCertificate $revoke,
        private RequestContext $context,
        private DemoRecords $records,
    ) {}

    public function install(DemoAccreditedResult $plan, DemoInsightsCast $cast, DemoAccreditedRequestSource $requests): Assessment
    {
        $assessment = $requests->completedRequestFor($plan, $cast->assessor);

        $this->writeFeedback($assessment, $cast->assessor);
        $this->writeSummary($assessment, $cast->assessor, $plan->assessorSummary);
        $this->settleCertificate($assessment, $plan->certificate, $cast->systemAdmin);
        $this->records->remember(DemoRecordKind::InsightsAssessment, $assessment->id);

        return $assessment;
    }

    private function writeFeedback(Assessment $assessment, User $assessor): void
    {
        $codes = array_slice($this->scorer->subCodesOf(CriteriaVersion::query()->findOrFail($assessment->criteria_version_id)), 0, self::FEEDBACK_SUBS);

        foreach ($codes as $code) {
            foreach (self::FEEDBACK as $kind => $body) {
                AssessmentFeedbackItem::query()->create([
                    'assessment_id' => $assessment->id,
                    'sub_code' => $code,
                    'kind' => FeedbackKind::from($kind),
                    'body' => $body,
                    'author_user_id' => $assessor->id,
                    'version' => 1,
                ]);
            }
        }
    }

    private function writeSummary(Assessment $assessment, User $assessor, ?string $summary): void
    {
        if ($summary === null) {
            return;
        }

        AssessmentSummary::query()->create([
            'assessment_id' => $assessment->id,
            'body' => $summary,
            'author_user_id' => $assessor->id,
            'version' => 1,
        ]);
    }

    private function settleCertificate(Assessment $assessment, DemoCertificateOutcome $outcome, User $systemAdmin): void
    {
        if ($outcome === DemoCertificateOutcome::None) {
            return;
        }

        $certificate = $this->certificates->handle($assessment, $systemAdmin->id);

        if ($outcome === DemoCertificateOutcome::Revoked) {
            $this->revokeWithItsOwnReason($certificate, $systemAdmin);
        }
    }

    private function revokeWithItsOwnReason(Certificate $certificate, User $systemAdmin): void
    {
        $reasonBefore = $this->context->reason();
        $this->revoke->handle($systemAdmin, $certificate, self::REVOKE_REASON);
        $this->context->recordReason($reasonBefore);
    }
}
