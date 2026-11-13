<?php

declare(strict_types=1);

namespace App\Support\Monitoring;

use Illuminate\Log\Logger;
use Monolog\Logger as MonologLogger;

final class RedactSensitiveLogData
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof MonologLogger) {
            $monolog->pushProcessor(new SensitiveLogProcessor);
        }
    }
}
