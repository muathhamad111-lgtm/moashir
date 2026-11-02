<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\Platform\DestructiveCommandVerdict;
use Illuminate\Database\Console\Seeds\SeedCommand;
use Illuminate\Support\Facades\DB;

final readonly class DestructiveCommandLock
{
    public function apply(DestructiveCommandVerdict $verdict): void
    {
        $this->prohibitDestructive(! $verdict->isAllowed());
        $this->prohibitSeeding(! $verdict->isAllowed());
    }

    public function prohibitDestructive(bool $prohibited): void
    {
        DB::prohibitDestructiveCommands($prohibited);
    }

    public function prohibitSeeding(bool $prohibited): void
    {
        SeedCommand::prohibit($prohibited);
    }
}
