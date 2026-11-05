<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Assessments\ReviewedAssessmentState;
use App\Data\Assessments\BoundSub;
use App\Data\Assessments\BoundSubCriteria;
use App\Data\Assessments\RemainingSub;
use App\Data\Assessments\SubmissionProgress;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCompletionRequest;

final readonly class SubmissionProgressReader
{
    public function __construct(private BoundSubCriteriaReader $subs, private SubmissionCoverageReader $coverage) {}

    public function read(Assessment $assessment): ?SubmissionProgress
    {
        if (! $assessment->status instanceof ReviewedAssessmentState || ! $assessment->status->acceptsEntityContent()) {
            return null;
        }

        $assessment->loadMissing('criteriaVersion');
        $counted = $this->countedSubs($assessment, $this->subs->of($assessment->criteriaVersion));
        $coverage = $this->coverage->read($assessment);
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
