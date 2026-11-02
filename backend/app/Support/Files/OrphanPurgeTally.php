<?php

declare(strict_types=1);

namespace App\Support\Files;

use App\Models\Files\StoredFile;

final class OrphanPurgeTally
{
    private int $files = 0;

    private int $bytes = 0;

    /**
     * @var array<string, int>
     */
    private array $byPurpose = [];

    public function __construct(private readonly int $graceHours) {}

    public function add(StoredFile $file): void
    {
        $purpose = $file->purpose->value;

        $this->files++;
        $this->bytes += $file->size_bytes;
        $this->byPurpose[$purpose] = ($this->byPurpose[$purpose] ?? 0) + 1;
    }

    public function isEmpty(): bool
    {
        return $this->files === 0;
    }

    /**
     * @return array{purged_files: int, purged_bytes: int, by_purpose: array<string, int>, grace_hours: int}
     */
    public function toArray(): array
    {
        $byPurpose = $this->byPurpose;
        ksort($byPurpose);

        return [
            'purged_files' => $this->files,
            'purged_bytes' => $this->bytes,
            'by_purpose' => $byPurpose,
            'grace_hours' => $this->graceHours,
        ];
    }
}
