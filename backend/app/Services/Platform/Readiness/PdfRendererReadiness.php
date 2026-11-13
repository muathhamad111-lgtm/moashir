<?php

declare(strict_types=1);

namespace App\Services\Platform\Readiness;

use App\Contracts\Platform\ReadinessCheck;
use App\Enums\Platform\ReadinessCheckStatus;
use App\Services\Documents\ChromiumAvailability;
use App\Services\Platform\LocalFakeAdapters;
use Illuminate\Contracts\Config\Repository;

final readonly class PdfRendererReadiness implements ReadinessCheck
{
    public function __construct(private ChromiumAvailability $chromium, private Repository $config, private ReadinessFailureLog $failures) {}

    public function name(): string
    {
        return 'pdf_renderer';
    }

    public function isCritical(): bool
    {
        return false;
    }

    public function status(): ReadinessCheckStatus
    {
        if ($this->config->get('moashir.documents.pdf_renderer') === LocalFakeAdapters::FAKE) {
            return ReadinessCheckStatus::Skipped;
        }

        return $this->chromium->isReady() ? ReadinessCheckStatus::Ok : $this->failures->record($this->name());
    }
}
