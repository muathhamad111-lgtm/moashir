<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Periods\SendPeriodDigest;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:periods:digest')]
final class SendPeriodDigestCommand extends Command
{
    protected $signature = 'moashir:periods:digest';

    protected $description = 'Send the daily accredited period digest (PER-04) to the Request Followers and System Administrators, at most once a day.';

    public function handle(SendPeriodDigest $action): int
    {
        $this->components->info($action->handle() ? 'The period digest was sent.' : 'No period digest is due.');

        return self::SUCCESS;
    }
}
