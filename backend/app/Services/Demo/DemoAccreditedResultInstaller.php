<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Assessments\OpenPaidAccreditedRequest;
use App\Actions\Certificates\IssueAccreditedCertificate;
use App\Actions\Certificates\RevokeCertificate;
use App\Data\Demo\DemoAccreditedResult;
use App\Data\Demo\DemoUserAccount;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\FeedbackKind;
use App\Enums\Assessments\ResultSnapshotKind;
use App\Enums\Platform\DemoCertificateOutcome;
use App\Exceptions\Console\DemoDataRefused;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentFeedbackItem;
use App\Models\Assessments\AssessmentSummary;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;

final readonly class DemoAccreditedResultInstaller
{
    private const array FEEDBACK = [
        FeedbackKind::Strength->value => 'توجد سياسة معتمدة ومحدّثة مع شواهد موثقة على تطبيقها (بيانات تجريبية).',
        FeedbackKind::Improvement->value => 'يُستحسن ربط الإجراءات بمؤشرات أداء دورية وتوثيق نتائج مراجعتها (بيانات تجريبية).',
    ];

    private const int FEEDBACK_SUBS = 4;

    private const string REVOKE_REASON = 'إلغاء تجريبي لعرض حالة الشهادة الملغاة في أدوات التميّز (بيانات تجريبية).';

    public function __construct(
        private DemoPaymentInstaller $payments,
        private OpenPaidAccreditedRequest $open,
        private DemoResultScorer $scorer,
        private IssueAccreditedCertificate $certificates,
        private RevokeCertificate $revoke,
    ) {}

    public function install(DemoAccreditedResult $plan, DemoUserAccount $owner, User $assessor, User $systemAdmin): Assessment
    {
        $payment = $this->payments->installPaid($owner);
        $assessment = $this->open->handle($payment->id) ?? throw DemoDataRefused::missingPrerequisite('the demo accredited request could not be opened');
        $completedAt = CarbonImmutable::parse($plan->completedOn);

        $this->scorer->score($assessment, CriteriaVersion::query()->findOrFail($assessment->criteria_version_id), $plan->profile, ResultSnapshotKind::AccreditedResult, $assessor->id);
        $assessment->forceFill([
            'status' => AccreditedAssessmentStatus::Completed,
            'submitted_at' => $completedAt->subWeeks(3),
            'completed_at' => $completedAt,
        ])->save();

        $this->writeFeedback($assessment, $assessor);
        $this->writeSummary($assessment, $assessor, $plan->assessorSummary);
        $this->settleCertificate($assessment, $plan->certificate, $systemAdmin);

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
            $this->revoke->handle($systemAdmin, $certificate, self::REVOKE_REASON);
        }
    }
}
