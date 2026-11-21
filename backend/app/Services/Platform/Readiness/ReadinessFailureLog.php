<?php

declare(strict_types=1);

namespace App\Services\Platform\Readiness;

use App\Enums\Platform\ReadinessCheckStatus;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class ReadinessFailureLog
{
    public function __construct(private LoggerInterface $logger) {}

    public function record(string $check, ?Throwable $failure = null): ReadinessCheckStatus
    {
        $this->logger->warning('platform.readiness_check_failed', [
            'check' => $check,
            'error' => $failure instanceof Throwable ? $failure::class : null,
        ]);

        return ReadinessCheckStatus::Failing;
    }
}
