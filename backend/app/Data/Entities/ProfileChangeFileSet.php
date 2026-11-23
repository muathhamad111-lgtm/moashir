<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\ProfileChangeFile;

/**
 * The logo and VAT certificate changes of one profile change request: a pending upload, a removal, or nothing.
 */
final readonly class ProfileChangeFileSet
{
    /**
     * @param  array<string, array{pending: int|null, removes: bool}>  $slots
     */
    public function __construct(private array $slots) {}

    public function pendingId(ProfileChangeFile $file): ?int
    {
        return $this->slots[$file->value]['pending'] ?? null;
    }

    public function removes(ProfileChangeFile $file): bool
    {
        return $this->slots[$file->value]['removes'] ?? false;
    }

    /**
     * @return list<ProfileChangeFile>
     */
    public function changed(): array
    {
        return array_values(array_filter(
            ProfileChangeFile::cases(),
            fn (ProfileChangeFile $file): bool => $this->pendingId($file) !== null || $this->removes($file),
        ));
    }

    /**
     * @return list<int>
     */
    public function pendingIds(): array
    {
        return array_values(array_filter(array_map($this->pendingId(...), ProfileChangeFile::cases())));
    }
}
