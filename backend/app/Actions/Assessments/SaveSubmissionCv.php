<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\BoundSub;
use App\Data\Assessments\BoundSubCriteria;
use App\Data\Assessments\CvSheet;
use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\CompletionItemKind;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\AccreditedDraftSaved;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\BoundSubCriteriaReader;
use App\Services\Assessments\CompletionScope;
use App\Services\Assessments\CvEntryWriter;
use App\Services\Assessments\EntityContentGate;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;

final readonly class SaveSubmissionCv
{
    public function __construct(
        private EntityContentGate $gate,
        private BoundSubCriteriaReader $subs,
        private CompletionScope $completion,
        private CvEntryWriter $writer,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param  array<array-key, string|null>  $bodiesBySubId
     */
    public function handle(User $actor, Assessment $assessment, array $bodiesBySubId, ?string $reason): CvSheet
    {
        return DB::transaction(function () use ($actor, $assessment, $bodiesBySubId, $reason): CvSheet {
            $locked = $this->gate->enter($actor, $assessment, $reason);
            $subs = $this->subs->of($locked->criteriaVersion);
            $changed = $this->writer->write($locked, $this->bodiesByCode($locked, $subs, $bodiesBySubId), $actor);

            if ($changed !== []) {
                $locked->touch();
                $this->audit->record(new AuditEntry(AuditEvent::AssessmentCvSaved, $locked, [], ['changed_codes' => $changed]));
                AccreditedDraftSaved::dispatch($locked);
            }

            return new CvSheet($locked, $subs, $this->writer->bodiesOf($locked));
        });
    }

    /**
     * @param  array<array-key, string|null>  $bodiesBySubId
     * @return array<string, string|null>
     */
    private function bodiesByCode(Assessment $locked, BoundSubCriteria $subs, array $bodiesBySubId): array
    {
        $bodies = [];

        foreach ($bodiesBySubId as $subId => $body) {
            $field = 'cv.'.$subId;
            $sub = is_numeric($subId) ? $subs->withId((int) $subId) : null;

            if (! $sub instanceof BoundSub) {
                throw AssessmentRejected::subNotInVersion($field);
            }

            if (! $sub->hasCv) {
                throw AssessmentRejected::cvNotAllowed($field);
            }

            $this->completion->assertRequested($locked, $sub->code, CompletionItemKind::Cv, $field);
            $bodies[$sub->code] = $body;
        }

        return $bodies;
    }
}
