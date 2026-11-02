<?php

declare(strict_types=1);

namespace App\Jobs\Files;

use App\Actions\Files\AbandonStoredFileScan;
use App\Actions\Files\ScanPendingStoredFile;
use App\Enums\Files\FileScanAttempt;
use App\Exceptions\Files\FileScannerUnavailable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

final class ScanStoredFileJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 6;

    public int $timeout = 120;

    public int $uniqueFor = 10800;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 1800, 3600];

    public function __construct(public readonly int $storedFileId) {}

    public function uniqueId(): string
    {
        return 'scan-stored-file:'.$this->storedFileId;
    }

    public function handle(ScanPendingStoredFile $scan): void
    {
        if ($scan->handle($this->storedFileId) === FileScanAttempt::Settled) {
            return;
        }

        if ($this->attempts() >= $this->tries) {
            $this->fail(FileScannerUnavailable::unreachable());

            return;
        }

        $this->release($this->backoff[min($this->attempts(), count($this->backoff)) - 1]);
    }

    public function failed(?Throwable $exception): void
    {
        app(AbandonStoredFileScan::class)->handle($this->storedFileId);
    }
}
