<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\SubmittedScores;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Events\Assessments\SelfAssessmentSubmitted;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentWriteGate;
use App\Services\Assessments\ScoreSheetReader;
use App\Services\Assessments\ScoringTreeBuilder;
use App\Services\Assessments\SelfResultFinalizer;
use App\Services\Assessments\SelfScoreRecorder;
use Illuminate\Support\Facades\DB;

final readonly class SubmitSelfAssessment
{
    public function __construct(
        private AssessmentWriteGate $gate,
        private ScoringTreeBuilder $trees,
        private SelfScoreRecorder $scores,
        private ScoreSheetReader $reader,
        private SelfResultFinalizer $finalizer,
    ) {}

    public function handle(User $actor, Assessment $assessment, ?SubmittedScores $scores, ?string $reason): Assessment
    {
        return DB::transaction(function () use ($actor, $assessment, $scores, $reason): Assessment {
            $locked = $this->gate->enter($actor, $assessment, SelfAssessmentStatus::Submitted, AssessmentVerb::Submit, $reason);

            if ($locked->awaitsCriteriaUpdate()) {
                throw AssessmentRejected::criteriaUpdateInProgress();
            }

            $tree = $this->trees->build($locked->criteriaVersion);

            if ($scores instanceof SubmittedScores) {
                $this->scores->record($actor, $locked, $tree, $scores);
            }

            $sheet = $this->reader->read($locked, $tree);
            $missing = $tree->unscoredUnits($sheet);

            if ($missing !== []) {
                throw AssessmentRejected::scoresIncomplete($missing);
            }

            $snapshot = $this->finalizer->finalize($actor, $locked, $tree, $sheet);
            SelfAssessmentSubmitted::dispatch($locked, $snapshot);

            return $locked;
        });
    }
}
