<?php

declare(strict_types=1);

namespace App\Enums\Platform;

use App\Data\Platform\DestructiveCommandTarget;

enum DestructiveCommandVerdict
{
    case Allowed;
    case ProtectedEnvironment;
    case NotTestDatabase;
    case ConfirmationRequired;

    public function isAllowed(): bool
    {
        return $this === self::Allowed;
    }

    public function explain(DestructiveCommandTarget $target): string
    {
        $database = $target->database ?? 'unknown';

        return match ($this) {
            self::Allowed => sprintf('Allowed on the database [%s].', $database),
            self::ProtectedEnvironment => sprintf('Refused: destructive database commands and seeding never run in the [%s] environment.', $target->environment),
            self::NotTestDatabase => sprintf('Refused: [%s] is not a test database. In the testing environment these commands only run on a database whose name ends with "_testing".', $database),
            self::ConfirmationRequired => sprintf('Refused: [%s] is not a test database. To run this command against it on purpose in the local environment, repeat it with --%s=%s.', $database, DestructiveDatabaseCommand::CONFIRMATION_OPTION, $database),
        };
    }
}
