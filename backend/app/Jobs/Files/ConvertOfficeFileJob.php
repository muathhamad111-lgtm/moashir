<?php

declare(strict_types=1);

namespace App\Jobs\Files;

use App\Exceptions\Watermark\ConversionSlotBusy;
use App\Models\Files\StoredFile;
use App\Services\Watermark\OfficeRenditions;
use App\Services\Watermark\WatermarkWorkspaces;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class ConvertOfficeFileJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    private const int BUSY_RETRY_SECONDS = 30;

    public int $tries = 20;

    public int $timeout = 300;

    public int $uniqueFor = 3600;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $storedFileId) {}

    public function uniqueId(): string
    {
        return 'office-rendition:'.$this->storedFileId;
    }

    public function handle(OfficeRenditions $renditions, WatermarkWorkspaces $workspaces): void
    {
        $file = StoredFile::query()->find($this->storedFileId);

        if (! $file instanceof StoredFile || ! $file->isScannedClean()) {
            return;
        }

        $workspace = $workspaces->open();

        try {
            $renditions->convertOnce($file, $workspace);
        } catch (ConversionSlotBusy) {
            $this->release(self::BUSY_RETRY_SECONDS);
        } finally {
            $workspace->close();
        }
    }
}
