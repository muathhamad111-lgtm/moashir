<?php

declare(strict_types=1);

namespace App\Actions\Certificates;

use App\Data\Files\SignedDownload;
use App\Exceptions\Certificates\CertificateRejected;
use App\Models\Certificates\Certificate;
use App\Models\Identity\User;
use App\Services\Certificates\CertificatePdfPublisher;
use App\Services\Files\SignedDownloadIssuer;

final readonly class IssueCertificateDownload
{
    public function __construct(private CertificatePdfPublisher $publisher, private SignedDownloadIssuer $downloads) {}

    public function handle(User $actor, Certificate $certificate): SignedDownload
    {
        if (! $certificate->isIssued()) {
            throw CertificateRejected::noLongerValid($certificate->status);
        }

        return $this->downloads->issue($this->publisher->ensure($certificate), $actor);
    }
}
