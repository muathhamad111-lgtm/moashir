<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Contracts\Demo\DemoAccreditedRequestSource;
use App\Data\Demo\DemoAccreditedResult;
use App\Data\Demo\DemoInsights;
use App\Data\Demo\DemoInsightsCast;
use App\Data\Demo\DemoPeople;
use App\Enums\Platform\DemoCertificateOutcome;

final readonly class DemoInsightsInstaller
{
    private const string ASSESSOR_SUMMARY = 'تتمتع الجهة بحوكمة واضحة وإدارة مالية منضبطة، وتحتاج إلى تطوير قياس الأثر وربط برامجها بمؤشرات نتائج دورية.';

    private const array SELF_PROFILE = [66, 58, 71, 62];

    private const string SELF_COMPLETED_ON = '2026-07-01 10:00:00';

    public function __construct(
        private DemoAccreditedResultInstaller $accredited,
        private DemoSelfResultInstaller $self,
        private DemoMaturityTextsInstaller $texts,
        private DemoPurchasedRequestSource $purchasedRequests,
        private DemoRecordedRequestSource $recordedRequests,
        private DemoPeriodInstaller $periods,
    ) {}

    public function installWithNewDemo(DemoPeople $people): DemoInsights
    {
        return $this->install($people->insightsCast(), $this->purchasedRequests);
    }

    public function installIntoInstalledDemo(DemoInsightsCast $cast): DemoInsights
    {
        return $this->install($cast, $this->recordedRequests);
    }

    private function install(DemoInsightsCast $cast, DemoAccreditedRequestSource $requests): DemoInsights
    {
        $this->periods->prepare();
        $plans = $this->accreditedPlans($cast);

        foreach ($plans as $plan) {
            $this->accredited->install($plan, $cast, $requests);
        }

        $this->self->install($cast->selfOnlyOwner, self::SELF_PROFILE, self::SELF_COMPLETED_ON);

        return new DemoInsights(
            accreditedResults: count($plans),
            revokedCertificates: count(array_filter($plans, static fn (DemoAccreditedResult $plan): bool => $plan->revokesCertificate())),
            assessorSummaries: count(array_filter($plans, static fn (DemoAccreditedResult $plan): bool => $plan->hasAssessorSummary())),
            selfResults: 1,
            maturityTextsFilled: $this->texts->install($cast->contentOfficer),
        );
    }

    /**
     * @return list<DemoAccreditedResult>
     */
    private function accreditedPlans(DemoInsightsCast $cast): array
    {
        return [
            new DemoAccreditedResult($cast->goldOwner, [80, 74, 78, 84], '2025-11-20 10:00:00', DemoCertificateOutcome::Issued),
            new DemoAccreditedResult($cast->goldOwner, [88, 79, 84, 90], '2026-09-15 10:00:00', DemoCertificateOutcome::Issued, self::ASSESSOR_SUMMARY),
            new DemoAccreditedResult($cast->mixedOwner, [55, 42, 60, 48], '2026-02-10 10:00:00', DemoCertificateOutcome::Issued),
            new DemoAccreditedResult($cast->mixedOwner, [68, 61, 74, 66], '2026-08-05 10:00:00', DemoCertificateOutcome::Revoked),
        ];
    }
}
