<?php

declare(strict_types=1);

namespace App\Events\Award;

use App\Models\Award\CommitteeDiscussion;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class CommitteeDiscussionOpened implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public CommitteeDiscussion $discussion, public int $actorId) {}
}
