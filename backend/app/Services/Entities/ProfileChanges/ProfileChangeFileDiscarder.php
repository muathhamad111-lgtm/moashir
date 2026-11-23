<?php

declare(strict_types=1);

namespace App\Services\Entities\ProfileChanges;

use App\Models\Files\StoredFile;
use App\Services\Files\StoredFileRemover;
use Illuminate\Support\Facades\DB;

/**
 * Removes pending profile files nobody will approve any more, once the transaction that dropped them commits.
 */
final readonly class ProfileChangeFileDiscarder
{
    public function __construct(private StoredFileRemover $remover) {}

    /**
     * @param  list<int|null>  $fileIds
     */
    public function discard(array $fileIds): void
    {
        $ids = array_values(array_filter($fileIds, static fn (?int $id): bool => $id !== null));

        if ($ids === []) {
            return;
        }

        DB::afterCommit(function () use ($ids): void {
            StoredFile::query()->whereKey($ids)->get()->each(fn (StoredFile $file) => $this->remover->remove($file));
        });
    }
}
