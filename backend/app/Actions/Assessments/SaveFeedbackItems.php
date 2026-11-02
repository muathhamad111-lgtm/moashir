<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\BoundSub;
use App\Data\Assessments\BoundSubCriteria;
use App\Data\Assessments\FeedbackChanges;
use App\Data\Assessments\FeedbackView;
use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Assessments\FeedbackKind;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AssigneeGuard;
use App\Services\Assessments\BoundSubCriteriaReader;
use App\Services\Assessments\FeedbackSheetReader;
use App\Services\Assessments\FeedbackSheetWriter;
use App\Services\Assessments\ReviewWriteGate;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class SaveFeedbackItems
{
    public function __construct(
        private ReviewWriteGate $gate,
        private AssigneeGuard $assignee,
        private BoundSubCriteriaReader $subs,
        private FeedbackSheetWriter $writer,
        private FeedbackSheetReader $reader,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Assessment $assessment, FeedbackChanges $changes, ?string $reason): FeedbackView
    {
        return DB::transaction(function () use ($actor, $assessment, $changes, $reason): FeedbackView {
            $underReview = $assessment->path->reviewStages()->underReview;
            $locked = $this->gate->enter($actor, $assessment, $underReview, AssessmentVerb::ScoreReview, $reason);
            $this->assignee->assertActsOn($actor, $locked);

            if ($locked->status !== $underReview) {
                throw IllegalStateTransition::from((string) $locked->status->value, AssessmentVerb::ScoreReview->value);
            }

            $subs = $this->subs->of($locked->criteriaVersion);
            $changed = $this->writer->write($locked, $this->byCode($subs, $changes), $actor);

            if ($changed !== []) {
                $locked->touch();
                $this->audit->record(new AuditEntry(AuditEvent::AssessmentFeedbackSaved, $locked, [], ['changed' => $changed]));
            }

            return new FeedbackView($locked, $this->reader->read($locked), $subs);
        });
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    private function byCode(BoundSubCriteria $subs, FeedbackChanges $changes): array
    {
        $byCode = [];

        foreach ($changes->fieldsBySubId as $subId => $fields) {
            $sub = is_numeric($subId) ? $subs->withId((int) $subId) : null;

            if (! $sub instanceof BoundSub) {
                throw AssessmentRejected::subNotInVersion('feedback.'.$subId);
            }

            foreach ($fields as $field => $body) {
                $kind = FeedbackKind::fromField($field);

                if ($kind instanceof FeedbackKind) {
                    $byCode[$sub->code][$kind->value] = $body;
                }
            }
        }

        return $byCode;
    }
}
