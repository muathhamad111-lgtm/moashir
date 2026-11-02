<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\Files\StoredFile;

final readonly class StoredFileRemover
{
    public function __construct(private StoredFileDisk $disk) {}

    public function remove(StoredFile $file): void
    {
        $this->disk->delete($file);
        $file->delete();
    }
}
