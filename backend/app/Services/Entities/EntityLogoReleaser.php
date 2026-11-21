<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Contracts\Entities\EntityLogoRetention;
use App\Models\Files\StoredFile;
use App\Services\Files\StoredFileRemover;
use Illuminate\Support\Facades\DB;

final readonly class EntityLogoReleaser
{
    public function __construct(private EntityLogoRetention $retention, private StoredFileRemover $remover) {}

    public function release(StoredFile $logo): void
    {
        if ($this->retention->isRetained($logo->id)) {
            return;
        }

        DB::afterCommit(fn () => $this->remover->remove($logo));
    }
}
