<?php

declare(strict_types=1);

namespace App\Data\Certificates;

use App\Enums\Certificates\VerificationOutcome;
use App\Models\Certificates\Certificate;

final readonly class CertificateVerification
{
    public function __construct(
        public VerificationOutcome $outcome,
        public ?Certificate $certificate = null,
    ) {}
}
