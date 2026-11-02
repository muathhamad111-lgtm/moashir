<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Award\CommitteeDiscussionStatus;
use App\Models\Assessments\Assessment;
use App\Models\Award\CommitteeDiscussion;
use Illuminate\Support\Facades\Date;

final class CommitteeDiscussionCanceller
{
    public function cancelOpen(Assessment $locked): ?CommitteeDiscussion
    {
        $open = CommitteeDiscussion::query()->where('assessment_id', $locked->id)->open()->lockForUpdate()->first();
        $open?->forceFill(['status' => CommitteeDiscussionStatus::Cancelled, 'closed_at' => Date::now()])->save();

        return $open;
    }
}
