<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Data\Demo\DemoActivity;
use App\Data\Demo\DemoPeople;
use App\Enums\Identity\RoleKey;

final readonly class DemoActivityInstaller
{
    public function __construct(
        private DemoPriceInstaller $prices,
        private DemoCycleInstaller $cycle,
        private DemoPaymentInstaller $payments,
        private DemoBankTransferInstaller $bankTransfers,
        private DemoInsightsInstaller $insights,
    ) {}

    public function install(DemoPeople $people): DemoActivity
    {
        $systemAdmin = $people->staffMember(RoleKey::SystemAdmin);
        $this->prices->install($systemAdmin);
        $cycle = $this->cycle->install($people->staffMember(RoleKey::AwardSecretary));
        [$paidEntity, $pendingEntity, $settledEntity] = $people->entityOwners;
        $accreditedResults = $this->insights->install($people);

        return new DemoActivity($cycle, [
            $this->payments->installPaid($paidEntity),
            ...$this->bankTransfers->install($systemAdmin, $people->staffMember(RoleKey::FinanceOfficer), $pendingEntity, $settledEntity),
        ], $accreditedResults);
    }
}
