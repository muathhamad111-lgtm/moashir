<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Data\Platform\DestructiveCommandTarget;
use App\Enums\Platform\DestructiveCommandVerdict;

final readonly class DestructiveCommandGuard
{
    private const string LOCAL = 'local';

    private const array DEVELOPMENT_ENVIRONMENTS = [self::LOCAL, 'testing'];

    private const string TEST_DATABASE_PATTERN = '/_testing(_test_\d+)?$/';

    public function verdict(DestructiveCommandTarget $target): DestructiveCommandVerdict
    {
        return match (true) {
            ! $this->isDevelopment($target->environment) => DestructiveCommandVerdict::ProtectedEnvironment,
            $this->isTestDatabase($target->database) => DestructiveCommandVerdict::Allowed,
            $target->environment !== self::LOCAL => DestructiveCommandVerdict::NotTestDatabase,
            $target->isConfirmed() => DestructiveCommandVerdict::Allowed,
            default => DestructiveCommandVerdict::ConfirmationRequired,
        };
    }

    public function seedingVerdict(string $environment): DestructiveCommandVerdict
    {
        return $this->isDevelopment($environment)
            ? DestructiveCommandVerdict::Allowed
            : DestructiveCommandVerdict::ProtectedEnvironment;
    }

    private function isDevelopment(string $environment): bool
    {
        return in_array($environment, self::DEVELOPMENT_ENVIRONMENTS, true);
    }

    private function isTestDatabase(?string $database): bool
    {
        return $database !== null && preg_match(self::TEST_DATABASE_PATTERN, $database) === 1;
    }
}
