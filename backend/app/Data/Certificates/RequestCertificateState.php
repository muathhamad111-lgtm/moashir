<?php

declare(strict_types=1);

namespace App\Data\Certificates;

use App\Enums\Certificates\CertificateIssuanceMode;
use App\Models\Certificates\Certificate;

final readonly class RequestCertificateState
{
    public function __construct(public ?Certificate $current, public CertificateIssuanceMode $issuanceMode) {}
}
