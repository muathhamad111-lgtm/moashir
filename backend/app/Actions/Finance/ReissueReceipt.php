<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Finance\ReceiptRejected;
use App\Models\Finance\PaymentReceipt;
use App\Services\Audit\AuditRecorder;
use App\Services\Finance\ReceiptPdfPublisher;
use App\Support\RequestContext;

final readonly class ReissueReceipt
{
    public function __construct(
        private ReceiptPdfPublisher $publisher,
        private RequestContext $context,
        private AuditRecorder $audit,
    ) {}

    public function handle(PaymentReceipt $receipt, string $reason): PaymentReceipt
    {
        if ($receipt->isVoided()) {
            throw ReceiptRejected::voided();
        }

        $previousCount = $receipt->reissue_count;
        $file = $this->publisher->regenerate($receipt);
        $this->context->recordReason($reason);

        $this->audit->record(new AuditEntry(AuditEvent::ReceiptReissued, $receipt, ['reissue_count' => $previousCount], [
            'reissue_count' => $receipt->reissue_count,
            'pdf_file_id' => $file->id,
            'number' => $receipt->number,
        ]));

        return $receipt;
    }
}
