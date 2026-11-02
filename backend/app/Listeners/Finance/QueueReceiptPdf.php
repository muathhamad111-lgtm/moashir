<?php

declare(strict_types=1);

namespace App\Listeners\Finance;

use App\Events\Finance\ReceiptIssued;
use App\Jobs\Finance\RenderReceiptPdfJob;

final class QueueReceiptPdf
{
    public function handle(ReceiptIssued $event): void
    {
        RenderReceiptPdfJob::dispatch($event->receipt->id);
    }
}
