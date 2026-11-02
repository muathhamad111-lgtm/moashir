<?php

declare(strict_types=1);

namespace App\Events\Files;

use App\Models\Files\StoredFile;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class StoredFileQuarantined implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public StoredFile $file) {}
}
