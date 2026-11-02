<?php

declare(strict_types=1);

namespace App\Jobs\Finance;

use App\Exceptions\Documents\DocumentRenderingUnavailable;
use App\Models\Finance\PaymentReceipt;
use App\Services\Finance\ReceiptPdfPublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

final class RenderReceiptPdfJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $receiptId) {}

    public function uniqueId(): string
    {
        return 'render-receipt-pdf:'.$this->receiptId;
    }

    public function handle(ReceiptPdfPublisher $publisher): void
    {
        $receipt = PaymentReceipt::query()->with('payment')->find($this->receiptId);

        if (! $receipt instanceof PaymentReceipt) {
            return;
        }

        try {
            $publisher->ensure($receipt);
        } catch (DocumentRenderingUnavailable) {
            Log::info('finance.receipt_pdf_deferred', ['receipt_id' => $this->receiptId]);
        }
    }
}
