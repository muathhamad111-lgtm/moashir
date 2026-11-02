<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Events\Award\SubmissionsClosed;
use App\Exceptions\IllegalStateTransition;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\AwardCycleRowLock;
use App\Services\Award\DraftExpirer;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class CloseCycleSubmissions
{
    private const string VERB = 'close_submissions';

    public function __construct(
        private StaffActionReason $reason,
        private AwardCycleRowLock $rowLock,
        private DraftExpirer $drafts,
        private AuditRecorder $audit,
    ) {}

    public function handle(?User $actor, AwardCycle $cycle, ?string $reason): AwardCycle
    {
        if ($actor instanceof User) {
            $this->reason->record($actor, $reason);
        }

        return DB::transaction(function () use ($actor, $cycle): AwardCycle {
            $locked = $this->rowLock->lock($cycle->id);

            if ($locked->status !== AwardCycleStatus::SubmissionOpen) {
                throw IllegalStateTransition::from($locked->status->value, self::VERB);
            }

            $now = Date::now();
            $previousEnd = $locked->submission_end->toIso8601String();

            if ($locked->submission_end->greaterThan($now)) {
                $locked->submission_end = $now->copy()->max($locked->submission_start->copy()->addSecond());
            }

            $locked->forceFill(['status' => AwardCycleStatus::SubmissionClosed, 'submissions_closed_at' => $now])->save();
            $expired = $this->drafts->expireDraftsOf($locked, $actor?->id);
            $this->audit->record(new AuditEntry(AuditEvent::CycleSubmissionsClosed, $locked, ['status' => AwardCycleStatus::SubmissionOpen->value, 'submission_end' => $previousEnd], [
                'status' => AwardCycleStatus::SubmissionClosed->value,
                'submission_end' => $locked->submission_end->toIso8601String(),
                'expired_drafts' => $expired,
                'trigger' => $actor instanceof User ? 'manual' : 'schedule',
            ]));

            SubmissionsClosed::dispatch($locked, $actor?->id);

            return $locked;
        });
    }
}
