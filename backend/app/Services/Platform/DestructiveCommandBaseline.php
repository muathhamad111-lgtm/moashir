<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Data\Platform\DestructiveCommandTarget;
use App\Support\ScalarValue;
use Illuminate\Contracts\Foundation\Application;

final readonly class DestructiveCommandBaseline
{
    public function __construct(
        private Application $app,
        private DestructiveCommandGuard $guard,
        private ConnectionDatabaseNames $databases,
        private DestructiveCommandLock $lock,
    ) {}

    public function apply(): void
    {
        $environment = ScalarValue::string($this->app->environment());

        $this->lock->prohibitDestructive(! $this->allowsEveryConnection($environment));
        $this->lock->prohibitSeeding(! $this->guard->seedingVerdict($environment)->isAllowed());
    }

    private function allowsEveryConnection(string $environment): bool
    {
        return array_all(
            $this->databases->ofEveryConnection(),
            fn (?string $database): bool => $this->guard->verdict(new DestructiveCommandTarget($environment, $database))->isAllowed(),
        );
    }
}
