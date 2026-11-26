<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Exceptions\Console\DemoDataRefused;
use App\Services\Platform\DemoPosture;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Log;

final readonly class DemoPurgeGuard
{
    public function __construct(private DemoPosture $posture, private AppendOnlyGuards $guards, private Repository $config) {}

    public function assertMayPreview(): void
    {
        if (! $this->posture->isOptedIn()) {
            $this->refuse(DemoDataRefused::purgeOutsideDemoPosture());
        }
    }

    public function assertMayPurge(): void
    {
        $this->assertMayPreview();

        if (! $this->guards->areOwnedByCurrentRole()) {
            $this->refuse(DemoDataRefused::purgeNeedsTableOwner());
        }
    }

    private function refuse(DemoDataRefused $refusal): never
    {
        Log::critical('platform.demo_data_refused', ['reason' => $refusal->reason, 'environment' => $this->config->get('app.env')]);

        throw $refusal;
    }
}
