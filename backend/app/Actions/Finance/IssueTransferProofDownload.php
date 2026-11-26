<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Files\SignedDownload;
use App\Models\Finance\BankTransfer;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Files\SignedDownloadIssuer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class IssueTransferProofDownload
{
    public function __construct(private SignedDownloadIssuer $downloads) {}

    public function handle(User $actor, Payment $payment): SignedDownload
    {
        $transfer = $payment->bankTransfer()->with('proofFile')->first();

        if (! $transfer instanceof BankTransfer) {
            throw new NotFoundHttpException;
        }

        return $this->downloads->issue($transfer->proofFile, $actor);
    }
}
