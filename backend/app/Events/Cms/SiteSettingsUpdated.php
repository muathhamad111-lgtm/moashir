<?php

declare(strict_types=1);

namespace App\Events\Cms;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class SiteSettingsUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public ?int $actorId) {}
}
