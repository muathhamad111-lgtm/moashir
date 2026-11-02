<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Identity\SyncPermissionCatalog;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:permissions:sync')]
final class SyncPermissionCatalogCommand extends Command
{
    protected $signature = 'moashir:permissions:sync';

    protected $description = 'Apply the code-defined permission catalog and default roles. Idempotent; run it on every deployment.';

    public function handle(SyncPermissionCatalog $syncCatalog): int
    {
        $syncCatalog->handle();

        $this->components->info(sprintf(
            'Permission catalog synchronised: %d permissions, %d roles.',
            count(PermissionKey::cases()),
            count(RoleKey::cases()),
        ));

        return self::SUCCESS;
    }
}
