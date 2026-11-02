<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\BreachedListImport;
use App\Data\Identity\BreachedListImportResult;
use App\Exceptions\Console\BreachedListUnreadable;
use App\Models\Identity\BreachedPassword;
use App\Support\ValueObjects\PasswordDigest;
use Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

final class ImportBreachedPasswords
{
    private const int CHUNK_SIZE = 1000;

    public function handle(BreachedListImport $import): BreachedListImportResult
    {
        $read = 0;
        $added = 0;
        $rejected = 0;

        foreach ($this->entries($import->path)->chunk(self::CHUNK_SIZE) as $chunk) {
            $digests = $chunk->map(static fn (string $entry): ?PasswordDigest => $import->format->digestOf($entry))->filter();

            $read += $chunk->count();
            $rejected += $chunk->count() - $digests->count();
            $added += $this->store($digests->values()->collect());
        }

        return new BreachedListImportResult($read, $added, $rejected);
    }

    /**
     * @return LazyCollection<int, string>
     */
    private function entries(string $path): LazyCollection
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw BreachedListUnreadable::at($path);
        }

        return LazyCollection::make(static function () use ($path): Generator {
            $handle = fopen($path, 'rb');

            if ($handle === false) {
                throw BreachedListUnreadable::at($path);
            }

            try {
                while (($line = fgets($handle)) !== false) {
                    yield rtrim($line, "\r\n");
                }
            } finally {
                fclose($handle);
            }
        })->reject(static fn (string $entry): bool => $entry === '');
    }

    /**
     * @param  Collection<int, PasswordDigest>  $digests
     */
    private function store(Collection $digests): int
    {
        $rows = $digests
            ->map(static fn (PasswordDigest $digest): string => $digest->value)
            ->unique()
            ->map(static fn (string $digest): array => ['digest' => $digest])
            ->values()
            ->all();

        return $rows === [] ? 0 : BreachedPassword::query()->insertOrIgnore($rows);
    }
}
