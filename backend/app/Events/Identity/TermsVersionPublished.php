<?php

declare(strict_types=1);

namespace App\Events\Identity;

use App\Enums\Identity\TermsKind;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class TermsVersionPublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public TermsKind $kind) {}
}
