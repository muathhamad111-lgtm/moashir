<?php

declare(strict_types=1);

namespace App\Listeners\Certificates;

use App\Events\Certificates\CertificateIssued;
use App\Jobs\Certificates\RenderCertificatePdfJob;

final readonly class RenderIssuedCertificate
{
    public function handle(CertificateIssued $event): void
    {
        RenderCertificatePdfJob::dispatch($event->certificate->id);
    }
}
