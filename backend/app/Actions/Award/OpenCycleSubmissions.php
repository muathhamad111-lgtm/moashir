<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Events\Award\CycleOpened;
use App\Exceptions\Award\AwardRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\AwardCycleRowLock;
use App\Services\Award\InitialAssessorLinks;
use App\Services\Award\RunningCycleGuard;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class OpenCycleSubmissions
{
    private const string VERB = 'open';

    public function __construct(
        private StaffActionReason $reason,
        private AwardCycleRowLock $rowLock,
        private RunningCycleGuard $running,
        private AuditRecorder $audit,
        private InitialAssessorLinks $initialAssessors,
    ) {}

    public function handle(?User $actor, AwardCycle $cycle, ?string $reason): AwardCycle
    {
        if ($actor instanceof User) {
            $this->reason->record($actor, $reason);
        }

        return DB::transaction(fn (): AwardCycle => $this->open($actor, $cycle));
    }

    private function open(?User $actor, AwardCycle $cycle): AwardCycle
    {
        $locked = $this->rowLock->lock($cycle->id);
        $now = Date::now();

        if (! $locked->status->allowsTransitionTo(AwardCycleStatus::SubmissionOpen)) {
            throw IllegalStateTransition::from($locked->status->value, self::VERB);
        }

        if ($locked->submission_end->lessThanOrEqualTo($now)) {
            throw AwardRejected::submissionWindowPassed();
        }

        if (! $this->initialAssessors->hasActive($locked->id)) {
            throw AwardRejected::noInitialAssessors();
        }

        $this->running->assertMayStart($locked, AwardCycleStatus::SubmissionOpen);
        $previousStart = $locked->submission_start->toIso8601String();

        if ($locked->submission_start->greaterThan($now)) {
            $locked->submission_start = $now;
        }

        $locked->forceFill(['status' => AwardCycleStatus::SubmissionOpen, 'opened_at' => $now])->save();
        $this->audit->record(new AuditEntry(AuditEvent::CycleOpened, $locked, ['status' => AwardCycleStatus::Draft->value, 'submission_start' => $previousStart], [
            'status' => AwardCycleStatus::SubmissionOpen->value,
            'submission_start' => $locked->submission_start->toIso8601String(),
            'trigger' => $actor instanceof User ? 'manual' : 'schedule',
        ]));

        CycleOpened::dispatch($locked, $actor?->id);

        return $locked;
    }
}
