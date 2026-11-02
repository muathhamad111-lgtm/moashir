<?php

declare(strict_types=1);

namespace App\Events\Entities;

use App\Models\Identity\EmailChangeRequest;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class EntityEmailChangeRejected implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public EmailChangeRequest $request) {}
}
