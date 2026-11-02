<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Models\Assessments\Assessment;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Award\SecretaryDecision;

final readonly class SecretaryRequestDetail
{
    /**
     * @param  list<CommitteeAssignment>  $assignments
     * @param  list<SecretaryDecision>  $decisions
     * @param  list<CommitteeDiscussion>  $discussions
     */
    public function __construct(
        public Assessment $assessment,
        public CommitteeTally $tally,
        public array $assignments,
        public array $decisions,
        public array $discussions,
    ) {}
}
