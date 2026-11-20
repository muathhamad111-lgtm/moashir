<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Models\Files\FileRendition;
use App\Models\Files\StoredFile;

final readonly class StoredFileRemover
{
    public function __construct(private StoredFileDisk $disk) {}

    public function remove(StoredFile $file): void
    {
        $this->removeRenditionOf($file);
        $this->disk->delete($file);
        $file->delete();
    }

    private function removeRenditionOf(StoredFile $file): void
    {
        $rendition = FileRendition::query()->with('rendition')->where('source_file_id', $file->id)->first();

        if (! $rendition instanceof FileRendition) {
            return;
        }

        $copy = $rendition->rendition;
        $rendition->delete();

        if ($copy instanceof StoredFile) {
            $this->disk->delete($copy);
            $copy->delete();
        }
    }
}
