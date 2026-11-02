<?php

declare(strict_types=1);

namespace App\Events\Assessments;

use App\Models\Assessments\EvidenceBundle;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class EvidenceBundleReady implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public EvidenceBundle $bundle) {}
}
