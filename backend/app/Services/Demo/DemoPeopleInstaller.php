<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Data\Demo\DemoPeople;
use App\Enums\Identity\RoleKey;

final readonly class DemoPeopleInstaller
{
    public function __construct(
        private DemoStaffInstaller $staff,
        private DemoTermsInstaller $terms,
        private DemoEntityInstaller $entities,
    ) {}

    public function install(): DemoPeople
    {
        $staff = $this->staff->install();
        $termsVersionIds = $this->terms->install($staff[RoleKey::SystemAdmin->value]->user);

        return new DemoPeople($staff, $this->entities->install($termsVersionIds));
    }
}
