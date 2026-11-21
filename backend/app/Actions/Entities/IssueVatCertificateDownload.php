<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Files\SignedDownload;
use App\Exceptions\Entities\VatCertificateRejected;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Files\SignedDownloadIssuer;

final readonly class IssueVatCertificateDownload
{
    public function __construct(private SignedDownloadIssuer $downloads) {}

    public function handle(User $viewer, Entity $entity): SignedDownload
    {
        $certificate = $entity->vatCertificate()->first();

        if (! $certificate instanceof StoredFile) {
            throw VatCertificateRejected::missing();
        }

        return $this->downloads->issue($certificate, $viewer);
    }
}
