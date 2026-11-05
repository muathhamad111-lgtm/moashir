<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Identity\ImportBreachedPasswords;
use App\Data\Identity\BreachedListImport;
use App\Data\Identity\BreachedListImportResult;
use App\Enums\Identity\BreachedListFormat;
use App\Exceptions\Console\BreachedListUnreadable;
use App\Support\ScalarValue;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'moashir:breached-passwords:import')]
final class ImportBreachedPasswordList extends Command
{
    private const string DIGEST_FILES = '*.sha256';

    protected $signature = 'moashir:breached-passwords:import
        {path : Path of a list file on this server, or of a directory whose *.sha256 files are all imported}
        {--format=plain : plain (one password per line) or sha256 (one SHA-256 hex digest per line); a directory is always sha256}';

    protected $description = 'Load a local breached-password list into the database. Makes no network call.';

    public function handle(ImportBreachedPasswords $import): int
    {
        $path = ScalarValue::string($this->argument('path'));
        $format = is_dir($path) ? BreachedListFormat::Sha256 : BreachedListFormat::tryFrom(ScalarValue::string($this->option('format')));

        if (! $format instanceof BreachedListFormat) {
            $this->components->error('Unknown format. Use plain or sha256.');

            return self::INVALID;
        }

        try {
            foreach ($this->filesAt($path) as $file) {
                $this->report(basename($file), $import->handle(new BreachedListImport($file, $format)));
            }
        } catch (BreachedListUnreadable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function filesAt(string $path): array
    {
        if (! is_dir($path)) {
            return [$path];
        }

        $files = glob(rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.self::DIGEST_FILES) ?: [];
        sort($files);

        return $files;
    }

    private function report(string $fileName, BreachedListImportResult $result): void
    {
        $this->components->info(sprintf(
            '%s: %d entries read: %d added, %d already listed, %d rejected as malformed.',
            $fileName,
            $result->read,
            $result->added,
            $result->alreadyListed(),
            $result->rejected,
        ));
    }
}
