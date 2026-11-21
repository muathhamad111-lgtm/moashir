<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Assessments\ReviewedAssessmentState;
use App\Data\Assessments\BoundSub;
use App\Data\Assessments\BoundSubCriteria;
use App\Data\Assessments\RemainingSub;
use App\Data\Assessments\SubmissionCoverage;
use App\Data\Assessments\SubmissionProgress;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;

final readonly class SubmissionProgressReader
{
    public function __construct(private BoundSubCriteriaReader $subs, private SubmissionCoverageReader $coverage) {}

    public function read(Assessment $assessment): ?SubmissionProgress
    {
        return $this->readMany([$assessment])[$assessment->id] ?? null;
    }

    /**
     * @param  list<Assessment>  $assessments
     * @return array<int, SubmissionProgress>
     */
    public function readMany(array $assessments): array
    {
        $tracked = array_values(array_filter($assessments, static fn (Assessment $assessment): bool => $assessment->status instanceof ReviewedAssessmentState && $assessment->status->acceptsEntityContent()));
        $coverage = $this->coverage->readMany(array_map(static fn (Assessment $assessment): int => $assessment->id, $tracked));
        $subsByVersion = [];
        $progress = [];

        foreach ($tracked as $assessment) {
            $subs = $subsByVersion[$assessment->criteria_version_id] ??= $this->subs->of($assessment->loadMissing('criteriaVersion')->criteriaVersion);
            $progress[$assessment->id] = $this->progressOf($this->countedSubs($assessment, $subs), $coverage[$assessment->id]);
        }

        return $progress;
    }

    /**
     * @param  list<BoundSub>  $counted
     */
    private function progressOf(array $counted, SubmissionCoverage $coverage): SubmissionProgress
    {
        $remaining = [];

        foreach ($counted as $sub) {
            if (! $coverage->covers($sub)) {
                $remaining[] = RemainingSub::of($sub, $coverage);
            }
        }

        usort($remaining, static fn (RemainingSub $first, RemainingSub $second): int => strnatcmp($first->code, $second->code));

        return new SubmissionProgress(count($counted), count($counted) - count($remaining), $remaining);
    }

    /**
     * @return list<BoundSub>
     */
    private function countedSubs(Assessment $assessment, BoundSubCriteria $subs): array
    {
        if ($assessment->status !== $assessment->path->reviewStages()->needsCompletion) {
            return $subs->subs;
        }

        $request = $assessment->loadMissing('openCompletionRequest')->openCompletionRequest;

        if (! $request instanceof AssessmentCompletionRequest) {
            return $subs->subs;
        }

        $requested = $request->requestedSubCodes();

        return array_values(array_filter($subs->subs, static fn (BoundSub $sub): bool => in_array($sub->code, $requested, true)));
    }
}
