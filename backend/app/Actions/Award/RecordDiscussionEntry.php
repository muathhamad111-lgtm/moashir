<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\DiscussionEntryKind;
use App\Events\Award\CommitteeDiscussionEntryRecorded;
use App\Exceptions\Award\CommitteeRejected;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Award\CommitteeDiscussionEntry;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class RecordDiscussionEntry
{
    public function __construct(private StaffActionReason $reason, private EntityFreezeGuard $freezeGuard, private AuditRecorder $audit) {}

    public function handle(User $actor, CommitteeDiscussion $discussion, DiscussionEntryKind $kind, ?string $body, ?string $reason): CommitteeDiscussionEntry
    {
        $this->reason->record($actor, $reason);

        return DB::transaction(function () use ($actor, $discussion, $kind, $body): CommitteeDiscussionEntry {
            $open = CommitteeDiscussion::query()->with('assessment.entity')->whereKey($discussion->id)->lockForUpdate()->firstOrFail();
            $this->freezeGuard->assertNotFrozen($open->assessment->entity);
            $this->assertAccepts($open, $kind);
            $member = $this->memberOf($actor, $open);

            CommitteeDiscussionEntry::query()
                ->where('committee_discussion_id', $open->id)
                ->where('cycle_committee_assessor_id', $member->id)
                ->whereNull('superseded_at')
                ->get()
                ->each(static fn (CommitteeDiscussionEntry $previous): bool => $previous->forceFill(['superseded_at' => Date::now()])->save());

            $entry = CommitteeDiscussionEntry::query()->create([
                'committee_discussion_id' => $open->id,
                'cycle_committee_assessor_id' => $member->id,
                'kind' => $kind,
                'body' => $kind->requiresBody() ? $body : null,
            ]);
            $this->audit->record(new AuditEntry(AuditEvent::CommitteeDiscussionEntry, $open, [], ['entry_id' => $entry->id, 'kind' => $kind->value]));

            CommitteeDiscussionEntryRecorded::dispatch($open, $entry);

            return $entry;
        });
    }

    private function assertAccepts(CommitteeDiscussion $open, DiscussionEntryKind $kind): void
    {
        if (! $open->isOpen()) {
            throw CommitteeRejected::discussionClosed();
        }

        if (! $open->acceptsEntriesAt(Date::now())) {
            throw CommitteeRejected::discussionDeadlinePassed();
        }

        if (! in_array($kind, $open->mode->entryKinds(), true)) {
            throw CommitteeRejected::entryKindInvalid();
        }
    }

    private function memberOf(User $actor, CommitteeDiscussion $open): CycleCommitteeAssessor
    {
        return CycleCommitteeAssessor::query()
            ->where('assessor_user_id', $actor->id)
            ->active()
            ->whereIn('id', CommitteeAssignment::query()->select('cycle_committee_assessor_id')->where('assessment_id', $open->assessment_id)->live())
            ->firstOr(static fn (): never => throw CommitteeRejected::membershipInactive());
    }
}
