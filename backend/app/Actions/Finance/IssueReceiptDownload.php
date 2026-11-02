<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Files\SignedDownload;
use App\Models\Finance\PaymentReceipt;
use App\Models\Identity\User;
use App\Services\Files\SignedDownloadIssuer;
use App\Services\Finance\ReceiptPdfPublisher;

final readonly class IssueReceiptDownload
{
    public function __construct(private ReceiptPdfPublisher $publisher, private SignedDownloadIssuer $downloads) {}

    public function handle(User $actor, PaymentReceipt $receipt): SignedDownload
    {
        return $this->downloads->issue($this->publisher->ensure($receipt), $actor);
    }
}
