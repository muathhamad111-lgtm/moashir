<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AssessmentScores;
use App\Data\Assessments\SubmittedScores;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Events\Assessments\SelfAssessmentDraftSaved;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentWriteGate;
use App\Services\Assessments\ScoringTreeBuilder;
use App\Services\Assessments\SelfScoreRecorder;
use Illuminate\Support\Facades\DB;

final readonly class SaveSelfAssessmentDraft
{
    public function __construct(
        private AssessmentWriteGate $gate,
        private ScoringTreeBuilder $trees,
        private SelfScoreRecorder $scores,
    ) {}

    public function handle(User $actor, Assessment $assessment, SubmittedScores $scores, ?string $reason): AssessmentScores
    {
        return DB::transaction(function () use ($actor, $assessment, $scores, $reason): AssessmentScores {
            $locked = $this->gate->enter($actor, $assessment, SelfAssessmentStatus::Draft, AssessmentVerb::SaveDraft, $reason);
            $tree = $this->trees->build($locked->criteriaVersion);
            $recorded = $this->scores->record($actor, $locked, $tree, $scores);

            SelfAssessmentDraftSaved::dispatch($locked, $recorded->changes->count());

            return new AssessmentScores($locked, $tree, $recorded->sheet);
        });
    }
}
