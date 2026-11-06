<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\ResultSnapshotKind;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Exceptions\Console\DemoDataRefused;
use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Services\Assessments\RequestNumberAllocator;
use Carbon\CarbonImmutable;

final readonly class DemoSelfResultInstaller
{
    public function __construct(private RequestNumberAllocator $numbers, private DemoResultScorer $scorer) {}

    /**
     * @param  list<int>  $profile
     */
    public function install(User $owner, array $profile, string $completedOn): Assessment
    {
        $version = CriteriaVersion::query()->published()->first() ?? throw DemoDataRefused::missingPrerequisite('no criteria version is published (moashir:criteria:install-seed)');
        $completedAt = CarbonImmutable::parse($completedOn);
        $assessment = new Assessment;
        $assessment->forceFill([
            'request_number' => $this->numbers->next(AssessmentPath::SelfAssessment),
            'entity_id' => $owner->entity_id,
            'path' => AssessmentPath::SelfAssessment,
            'status' => SelfAssessmentStatus::Submitted,
            'criteria_version_id' => $version->id,
            'scale_kind' => $version->scale_kind,
            'submitted_at' => $completedAt,
        ])->save();

        $this->scorer->score($assessment, $version, $profile, ResultSnapshotKind::SelfResult, null);
        $assessment->forceFill(['status' => SelfAssessmentStatus::ResultAvailable, 'completed_at' => $completedAt])->save();

        return $assessment;
    }
}
