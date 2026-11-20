<?php

declare(strict_types=1);

namespace App\Actions\Files;

use App\Enums\Files\StoredFilePurpose;
use App\Enums\Files\WatermarkFormat;
use App\Jobs\Files\ConvertOfficeFileJob;
use App\Models\Files\FileRendition;
use App\Models\Files\StoredFile;

final readonly class QueueOfficeRenditions
{
    private const int CHUNK = 500;

    public function handle(): int
    {
        $queued = 0;

        StoredFile::query()
            ->clean()
            ->whereIn('purpose', array_map(static fn (StoredFilePurpose $purpose): string => $purpose->value, StoredFilePurpose::watermarked()))
            ->whereIn('mime_type', WatermarkFormat::officeMimeTypes())
            ->whereNotIn('id', FileRendition::query()->select('source_file_id'))
            ->select(['id'])
            ->lazyById(self::CHUNK)
            ->each(static function (StoredFile $file) use (&$queued): void {
                ConvertOfficeFileJob::dispatch($file->id);
                $queued++;
            });

        return $queued;
    }
}
