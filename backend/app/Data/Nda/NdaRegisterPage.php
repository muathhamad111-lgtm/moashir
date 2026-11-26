<?php

declare(strict_types=1);

namespace App\Data\Nda;

use App\Models\Identity\TermsAcceptance;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class NdaRegisterPage
{
    /**
     * @param  LengthAwarePaginator<int, User>  $users
     * @param  array<int, TermsAcceptance>  $acceptances  keyed by user id
     * @param  array<int, string>  $lastAccepted  the latest accepted version label, keyed by user id
     */
    public function __construct(
        public ?TermsVersion $version,
        public LengthAwarePaginator $users,
        public array $acceptances,
        public NdaRegisterSummary $summary,
        public array $lastAccepted = [],
    ) {}
}
