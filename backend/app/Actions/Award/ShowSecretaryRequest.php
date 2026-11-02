<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Award\SecretaryRequestDetail;
use App\Models\Assessments\Assessment;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Award\SecretaryDecision;
use App\Services\Award\CommitteeTallyReader;

final readonly class ShowSecretaryRequest
{
    public function __construct(private CommitteeTallyReader $tallies) {}

    public function handle(Assessment $assessment): SecretaryRequestDetail
    {
        $assessment->loadMissing(['entity', 'region', 'currentResult', 'committeeAverage', 'awardCycle']);

        return new SecretaryRequestDetail(
            $assessment,
            $this->tallies->tally($assessment),
            array_values(CommitteeAssignment::query()->with('member.assessor')->where('assessment_id', $assessment->id)->orderBy('id')->get()->all()),
            array_values(SecretaryDecision::query()->with('decidedBy')->where('assessment_id', $assessment->id)->orderBy('id')->get()->all()),
            array_values(CommitteeDiscussion::query()->with(['currentEntries.member.assessor', 'openedBy'])->where('assessment_id', $assessment->id)->orderByDesc('id')->get()->all()),
        );
    }
}
