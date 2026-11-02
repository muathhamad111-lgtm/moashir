<?php

declare(strict_types=1);

namespace App\Actions\Finance;

use App\Data\Files\SignedDownload;
use App\Exceptions\Finance\FinanceExportRejected;
use App\Models\Files\StoredFile;
use App\Models\Finance\FinanceExport;
use App\Models\Identity\User;
use App\Services\Files\SignedDownloadIssuer;

final readonly class IssueExportDownload
{
    public function __construct(private SignedDownloadIssuer $downloads) {}

    public function handle(User $actor, FinanceExport $export): SignedDownload
    {
        $file = $export->storedFile;

        if (! $file instanceof StoredFile) {
            throw FinanceExportRejected::notReady();
        }

        return $this->downloads->issue($file, $actor);
    }
}
