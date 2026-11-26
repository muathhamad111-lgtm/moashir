<?php

declare(strict_types=1);

namespace App\Services\Platform\Readiness;

use App\Contracts\Platform\ReadinessCheck;
use App\Enums\Platform\ReadinessCheckStatus;
use Illuminate\Database\ConnectionResolverInterface;
use Throwable;

final readonly class DatabaseReadiness implements ReadinessCheck
{
    public function __construct(private ConnectionResolverInterface $database, private ReadinessFailureLog $failures) {}

    public function name(): string
    {
        return 'database';
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function status(): ReadinessCheckStatus
    {
        try {
            $this->database->connection()->select('select 1');
        } catch (Throwable $failure) {
            return $this->failures->record($this->name(), $failure);
        }

        return ReadinessCheckStatus::Ok;
    }
}
