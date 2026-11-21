<?php

declare(strict_types=1);

namespace App\Data\Certificates;

use App\Enums\Certificates\CertificateLevel;
use App\Enums\Certificates\CertificateStatus;

final readonly class CertificateListFilters
{
    /**
     * @param  list<CertificateStatus>  $statuses
     */
    public function __construct(
        public array $statuses = [],
        public ?CertificateLevel $level = null,
        public ?int $entityId = null,
        public ?int $assessmentId = null,
        public ?string $number = null,
        public int $perPage = 15,
    ) {}
}
