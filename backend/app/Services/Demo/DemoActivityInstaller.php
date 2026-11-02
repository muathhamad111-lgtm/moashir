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
    ) {}

    public function install(DemoPeople $people): DemoActivity
    {
        $this->prices->install($people->staffMember(RoleKey::SystemAdmin));
        $cycle = $this->cycle->install($people->staffMember(RoleKey::AwardSecretary));
        [$paidEntity, $pendingEntity] = $people->entityOwners;

        return new DemoActivity($cycle, [
            $this->payments->installPaid($paidEntity),
            $this->payments->installOpen($pendingEntity),
        ]);
    }
}
