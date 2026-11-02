<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Identity\SyncPermissionCatalog;
use Illuminate\Database\Seeder;

final class PermissionCatalogSeeder extends Seeder
{
    public function run(SyncPermissionCatalog $syncCatalog): void
    {
        $syncCatalog->handle();
    }
}
