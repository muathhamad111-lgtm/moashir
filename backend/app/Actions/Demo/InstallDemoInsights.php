<?php

declare(strict_types=1);

namespace App\Actions\Demo;

use App\Data\Demo\DemoInsights;
use App\Services\Demo\DemoInsightsCastResolver;
use App\Services\Demo\DemoInsightsInstaller;
use App\Services\Demo\DemoInstallGuard;
use App\Services\Demo\DemoNotificationsMute;
use App\Services\Platform\TransactionLock;
use Illuminate\Support\Facades\DB;

final readonly class InstallDemoInsights
{
    private const string LOCK = 'demo:insights';

    public function __construct(
        private DemoInstallGuard $guard,
        private DemoInsightsCastResolver $cast,
        private DemoInsightsInstaller $insights,
        private TransactionLock $lock,
        private DemoNotificationsMute $notifications,
    ) {}

    public function handle(): DemoInsights
    {
        $this->guard->assertMayInstallInsights();

        return $this->notifications->during(fn (): DemoInsights => DB::transaction(fn (): DemoInsights => $this->install()));
    }

    private function install(): DemoInsights
    {
        $this->lock->lock(self::LOCK);
        $this->guard->assertMayInstallInsights();

        return $this->insights->installIntoInstalledDemo($this->cast->resolve());
    }
}
