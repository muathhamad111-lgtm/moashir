<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Data\Demo\DemoAccreditedResult;
use App\Data\Demo\DemoPeople;
use App\Enums\Identity\RoleKey;
use App\Enums\Platform\DemoCertificateOutcome;

final readonly class DemoInsightsInstaller
{
    private const string ASSESSOR_SUMMARY = 'تتمتع الجهة بحوكمة واضحة وإدارة مالية منضبطة، وتحتاج إلى تطوير قياس الأثر وربط برامجها بمؤشرات نتائج دورية (ملخص تجريبي).';

    public function __construct(
        private DemoAccreditedResultInstaller $accredited,
        private DemoSelfResultInstaller $self,
        private DemoMaturityTextsInstaller $texts,
    ) {}

    public function install(DemoPeople $people): int
    {
        [$goldEntity, $selfOnlyEntity, $mixedEntity] = $people->entityOwners;
        $assessor = $people->staffMember(RoleKey::Assessor);
        $systemAdmin = $people->staffMember(RoleKey::SystemAdmin);
        $plans = [
            [$goldEntity, new DemoAccreditedResult([80, 74, 78, 84], '2025-11-20 10:00:00', DemoCertificateOutcome::Issued)],
            [$goldEntity, new DemoAccreditedResult([88, 79, 84, 90], '2026-09-15 10:00:00', DemoCertificateOutcome::Issued, self::ASSESSOR_SUMMARY)],
            [$mixedEntity, new DemoAccreditedResult([55, 42, 60, 48], '2026-02-10 10:00:00', DemoCertificateOutcome::Issued)],
            [$mixedEntity, new DemoAccreditedResult([68, 61, 74, 66], '2026-08-05 10:00:00', DemoCertificateOutcome::Revoked)],
        ];

        foreach ($plans as [$owner, $plan]) {
            $this->accredited->install($plan, $owner, $assessor, $systemAdmin);
        }

        $this->self->install($selfOnlyEntity->user, [66, 58, 71, 62], '2026-07-01 10:00:00');
        $this->texts->install($people->staffMember(RoleKey::ContentOfficer));

        return count($plans);
    }
}
