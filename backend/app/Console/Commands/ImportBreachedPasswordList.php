<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Identity\ImportBreachedPasswords;
use App\Data\Identity\BreachedListImport;
use App\Enums\Identity\BreachedListFormat;
use App\Exceptions\Console\BreachedListUnreadable;
use App\Support\ScalarValue;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:breached-passwords:import')]
final class ImportBreachedPasswordList extends Command
{
    protected $signature = 'moashir:breached-passwords:import
        {path : Path of the list file on this server}
        {--format=plain : plain (one password per line) or sha256 (one SHA-256 hex digest per line)}';

    protected $description = 'Load a local breached-password list into the database. Makes no network call.';

    public function handle(ImportBreachedPasswords $import): int
    {
        $format = BreachedListFormat::tryFrom(ScalarValue::string($this->option('format')));

        if (! $format instanceof BreachedListFormat) {
            $this->components->error('Unknown format. Use plain or sha256.');

            return self::INVALID;
        }

        try {
            $result = $import->handle(new BreachedListImport(ScalarValue::string($this->argument('path')), $format));
        } catch (BreachedListUnreadable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%d entries read: %d added, %d already listed, %d rejected as malformed.',
            $result->read,
            $result->added,
            $result->alreadyListed(),
            $result->rejected,
        ));

        return self::SUCCESS;
    }
}
