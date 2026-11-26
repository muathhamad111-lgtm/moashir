<?php

declare(strict_types=1);

namespace App\Events\Cms;

use App\Enums\Cms\CmsPageKey;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class CmsPageUnpublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public CmsPageKey $page, public ?int $actorId) {}
}
