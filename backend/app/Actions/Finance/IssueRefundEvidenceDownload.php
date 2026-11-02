<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Files\SignedDownload;
use App\Models\Finance\Refund;
use App\Models\Identity\User;
use App\Services\Files\SignedDownloadIssuer;

final readonly class IssueRefundEvidenceDownload
{
    public function __construct(private SignedDownloadIssuer $downloads) {}

    public function handle(User $actor, Refund $refund): SignedDownload
    {
        return $this->downloads->issue($refund->evidenceFile, $actor);
    }
}
