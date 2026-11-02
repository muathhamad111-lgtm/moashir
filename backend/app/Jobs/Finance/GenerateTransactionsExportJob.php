<?php

declare(strict_types=1);

namespace App\Jobs\Finance;

use App\Actions\Finance\GenerateTransactionsExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Throwable;

final class GenerateTransactionsExportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $exportId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return 'generate-transactions-export:'.$this->exportId;
    }

    public function handle(GenerateTransactionsExport $generate): void
    {
        $generate->handle($this->exportId);
    }

    public function failed(?Throwable $exception): void
    {
        app(GenerateTransactionsExport::class)->markFailed($this->exportId);
    }
}
