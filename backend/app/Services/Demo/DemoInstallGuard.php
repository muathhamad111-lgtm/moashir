<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Exceptions\Console\DemoDataRefused;
use App\Services\Platform\DemoPosture;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Log;

final readonly class DemoInstallGuard
{
    public function __construct(private Repository $config, private DemoRecords $records, private DemoPosture $posture) {}

    public function assertMayInstall(): void
    {
        $this->assertEnabled();

        if ($this->records->isInstalled()) {
            $this->refuse(DemoDataRefused::alreadyInstalled());
        }

        $foreignEntities = $this->records->countEntitiesNotCreatedByTheDemo();

        if ($foreignEntities > 0) {
            $this->refuse(DemoDataRefused::realDataPresent($foreignEntities));
        }
    }

    public function assertMayInstallContent(): void
    {
        $this->assertMayRotate();
    }

    public function assertMayInstallInsights(): void
    {
        $this->assertMayInstallContent();

        if ($this->records->hasInsightsCases()) {
            $this->refuse(DemoDataRefused::insightsAlreadyInstalled());
        }
    }

    public function assertMayRotate(): void
    {
        $this->assertEnabled();

        if (! $this->records->isInstalled()) {
            $this->refuse(DemoDataRefused::notInstalled());
        }
    }

    private function assertEnabled(): void
    {
        if ($this->config->get('moashir.demo.enabled') !== true) {
            $this->refuse(DemoDataRefused::flagNotSet());
        }

        if (! $this->posture->allowsDemoTooling()) {
            $this->refuse(DemoDataRefused::postureNotDemo());
        }
    }

    private function refuse(DemoDataRefused $refusal): never
    {
        Log::critical('platform.demo_data_refused', ['reason' => $refusal->reason, 'environment' => $this->config->get('app.env')]);

        throw $refusal;
    }
}
