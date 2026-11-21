<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Enums\Certificates\CertificateLevel;
use App\Support\ValueObjects\Percent;

final readonly class PreviewedResult
{
    public function __construct(public Percent $percent, public ?CertificateLevel $level) {}
}
