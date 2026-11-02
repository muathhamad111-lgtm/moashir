<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Identity\ExpireInvitations;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:expire-invitations')]
final class ExpireStaffInvitations extends Command
{
    protected $signature = 'moashir:expire-invitations';

    protected $description = 'Mark staff invitations past their validity as expired.';

    public function handle(ExpireInvitations $expireInvitations): int
    {
        $this->components->info(sprintf('%d invitation(s) expired.', $expireInvitations->handle()));

        return self::SUCCESS;
    }
}
