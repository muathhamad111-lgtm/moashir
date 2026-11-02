<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Identity\ImportBreachedPasswords;
use App\Data\Identity\BreachedListImport;
use App\Enums\Identity\BreachedListFormat;
use Illuminate\Database\Seeder;

final class BreachedPasswordSeeder extends Seeder
{
    public const string CURATED_LIST = 'data/breached-passwords/curated-seed.sha256';

    public function run(ImportBreachedPasswords $import): void
    {
        $import->handle(new BreachedListImport(database_path(self::CURATED_LIST), BreachedListFormat::Sha256));
    }
}
