<?php

declare(strict_types=1);

namespace App\Events\Certificates;

use App\Models\Certificates\Certificate;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class CertificateSuspended implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Certificate $certificate) {}
}
