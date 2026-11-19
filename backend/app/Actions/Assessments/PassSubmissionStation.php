<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Criteria\MainCriterion;
use App\Models\Identity\User;
use App\Services\Assessments\EntityContentGate;
use App\Services\Assessments\PassedStations;
use Illuminate\Support\Facades\DB;

final readonly class PassSubmissionStation
{
    private const string FIELD = 'main_criterion_id';

    public function __construct(private EntityContentGate $gate, private PassedStations $stations) {}

    /**
     * @return list<int> the passed station ids of the assessment
     */
    public function handle(User $actor, Assessment $assessment, int $mainCriterionId, ?string $reason): array
    {
        return DB::transaction(function () use ($actor, $assessment, $mainCriterionId, $reason): array {
            $locked = $this->gate->enter($actor, $assessment, $reason);
            $this->assertInVersion($locked, $mainCriterionId);
            $this->stations->pass($locked, $mainCriterionId);

            return $this->stations->of($locked);
        });
    }

    private function assertInVersion(Assessment $assessment, int $mainCriterionId): void
    {
        $isBound = MainCriterion::query()
            ->whereKey($mainCriterionId)
            ->where('criteria_version_id', $assessment->criteria_version_id)
            ->exists();

        if (! $isBound) {
            throw AssessmentRejected::mainNotInVersion(self::FIELD);
        }
    }
}
