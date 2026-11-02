<?php

declare(strict_types=1);

namespace App\Actions\Demo;

use App\Data\Demo\DemoAccount;
use App\Data\Demo\DemoInstallation;
use App\Services\Demo\DemoAccountsDelivery;
use App\Services\Demo\DemoActivityInstaller;
use App\Services\Demo\DemoInstallGuard;
use App\Services\Demo\DemoPeopleInstaller;

final readonly class InstallDemoData
{
    public function __construct(
        private DemoInstallGuard $guard,
        private DemoAccountsDelivery $delivery,
        private DemoPeopleInstaller $people,
        private DemoActivityInstaller $activity,
    ) {}

    public function handle(string $accountsFilePath): DemoInstallation
    {
        $this->guard->assertMayInstall();

        return $this->delivery->deliver(
            $accountsFilePath,
            function (): DemoInstallation {
                $this->guard->assertMayInstall();
                $people = $this->people->install();

                return new DemoInstallation($people, $this->activity->install($people));
            },
            /** @return list<DemoAccount> */
            static fn (DemoInstallation $installation): array => $installation->people->accounts(),
        );
    }
}
