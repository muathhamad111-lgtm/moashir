<?php

declare(strict_types=1);

namespace App\Events\Award;

use App\Models\Award\CommitteeDiscussion;
use App\Models\Award\CommitteeDiscussionEntry;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class CommitteeDiscussionEntryRecorded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public CommitteeDiscussion $discussion, public CommitteeDiscussionEntry $entry) {}
}
