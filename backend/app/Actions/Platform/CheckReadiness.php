<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Data\Platform\ReadinessReport;
use App\Data\Platform\ReadinessViewer;
use App\Services\Platform\Readiness\ReadinessProbe;
use App\Services\Platform\ReadinessDetailsAccess;

final readonly class CheckReadiness
{
    public function __construct(private ReadinessProbe $probe, private ReadinessDetailsAccess $access) {}

    public function handle(ReadinessViewer $viewer): ReadinessReport
    {
        return $this->probe->run($this->access->allows($viewer));
    }
}
