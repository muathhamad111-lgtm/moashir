<?php

declare(strict_types=1);

namespace App\Services\Watermark;

use App\Models\Files\StoredFile;
use App\Services\Files\StoredFileDisk;
use RuntimeException;

final readonly class WatermarkSourceCopier
{
    public function __construct(private StoredFileDisk $disk) {}

    public function copy(StoredFile $file, string $target): string
    {
        $source = $this->disk->readStream($file);
        $destination = fopen($target, 'wb');

        try {
            if ($destination === false || stream_copy_to_stream($source, $destination) === false) {
                throw new RuntimeException('The file could not be copied into the watermark workspace.');
            }
        } finally {
            fclose($source);

            if (is_resource($destination)) {
                fclose($destination);
            }
        }

        return $target;
    }
}
