<?php

declare(strict_types=1);

namespace App\Services\Watermark;

use App\Data\Files\FileDownload;
use App\Enums\Files\AccessIntent;
use App\Exceptions\Watermark\WatermarkFailed;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;

final readonly class WatermarkedFileDelivery
{
    public function __construct(private WatermarkWorkspaces $workspaces, private FileStamper $stamper) {}

    public function deliver(User $viewer, StoredFile $file, AccessIntent $intent): FileDownload
    {
        $workspace = $this->workspaces->open();

        try {
            $stamped = $this->stamper->stamp($viewer, $file, $intent, $workspace);
            $stream = fopen($stamped->path, 'rb');
            $size = filesize($stamped->path);

            if ($stream === false || $size === false) {
                throw WatermarkFailed::unreadable();
            }
        } finally {
            $workspace->close();
        }

        return new FileDownload($stream, $stamped->fileName, $stamped->mimeType, $size);
    }
}
