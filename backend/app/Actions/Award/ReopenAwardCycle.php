<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Exceptions\Award\AwardRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\AwardCycleRowLock;
use App\Services\Award\RunningCycleGuard;
use App\Support\ScalarValue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class ReopenAwardCycle
{
    private const string VERB = 'reopen';

    public function __construct(
        private StaffActionReason $reason,
        private AwardCycleRowLock $rowLock,
        private RunningCycleGuard $running,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, string $reason): AwardCycle
    {
        $this->reason->record($actor, $reason);

        try {
            return DB::transaction(fn (): AwardCycle => $this->reopen($cycle));
        } catch (UniqueConstraintViolationException) {
            throw AwardRejected::anotherCycleRunning(ScalarValue::int(AwardCycle::query()->running()->value('id')));
        }
    }

    private function reopen(AwardCycle $cycle): AwardCycle
    {
        $locked = $this->rowLock->lock($cycle->id);
        $target = $locked->closed_from_status;

        if ($locked->status !== AwardCycleStatus::Closed || ! $target instanceof AwardCycleStatus) {
            throw IllegalStateTransition::from($locked->status->value, self::VERB);
        }

        $this->running->assertNoOtherRunning($locked);
        $locked->forceFill(['status' => $target, 'closed_at' => null, 'closed_from_status' => null])->save();
        $this->audit->record(new AuditEntry(AuditEvent::CycleReopened, $locked, ['status' => AwardCycleStatus::Closed->value], ['status' => $target->value]));

        return $locked;
    }
}
