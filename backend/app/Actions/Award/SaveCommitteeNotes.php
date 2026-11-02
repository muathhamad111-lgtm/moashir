<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Assessments\BoundSub;
use App\Data\Audit\AuditEntry;
use App\Data\Award\CommitteeNoteChanges;
use App\Data\Award\CommitteeNotes;
use App\Enums\Audit\AuditEvent;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Models\Award\CommitteeAssignment;
use App\Models\Identity\User;
use App\Services\Assessments\BoundSubCriteriaReader;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\CommitteeNotesReader;
use App\Services\Award\CommitteeNotesWriter;
use App\Services\Award\CommitteeWorkStarter;
use App\Services\Award\CommitteeWriteGate;
use Illuminate\Support\Facades\DB;

final readonly class SaveCommitteeNotes
{
    public function __construct(
        private CommitteeWriteGate $gate,
        private CommitteeWorkStarter $starter,
        private BoundSubCriteriaReader $subs,
        private CommitteeNotesWriter $writer,
        private CommitteeNotesReader $reader,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, CommitteeAssignment $assignment, CommitteeNoteChanges $changes, ?string $reason): CommitteeNotes
    {
        return DB::transaction(function () use ($actor, $assignment, $changes, $reason): CommitteeNotes {
            $locked = $this->gate->enter($actor, $assignment, 'note_committee', $reason);
            $this->assertSubsInVersion($locked, $changes);
            $this->starter->begin($locked, $actor->id);
            $changed = $this->writer->apply($locked, $changes, $actor);

            if ($changed !== []) {
                $locked->touch();
                $this->audit->record(new AuditEntry(AuditEvent::CommitteeNotesSaved, $locked, [], ['changed' => $changed]));
            }

            return $this->reader->read($locked);
        });
    }

    private function assertSubsInVersion(CommitteeAssignment $locked, CommitteeNoteChanges $changes): void
    {
        $bound = $this->subs->of($locked->assessment->criteriaVersion);

        foreach (array_keys($changes->subs) as $subId) {
            if (! $bound->withId($subId) instanceof BoundSub) {
                throw AssessmentRejected::subNotInVersion('notes.'.$subId);
            }
        }
    }
}
