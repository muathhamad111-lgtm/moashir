<?php

declare(strict_types=1);

namespace App\Support\Files;

use App\Models\Files\StoredFile;

final class PublicAssetLink
{
    /**
     * @return array{file_id: string, url: string}|null
     */
    public static function of(?StoredFile $file): ?array
    {
        if (! $file instanceof StoredFile || ! $file->isScannedClean()) {
            return null;
        }

        return [
            'file_id' => $file->public_id,
            'url' => route('content.files.show', ['asset' => $file->public_id], false),
        ];
    }
}
