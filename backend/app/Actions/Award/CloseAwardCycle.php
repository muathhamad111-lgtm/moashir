<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Events\Award\CycleClosed;
use App\Exceptions\Award\AwardRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\AwardCycleRowLock;
use App\Services\Award\CycleOpenRequests;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class CloseAwardCycle
{
    private const string VERB = 'close';

    public function __construct(
        private StaffActionReason $reason,
        private AwardCycleRowLock $rowLock,
        private CycleOpenRequests $openRequests,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, ?string $reason): AwardCycle
    {
        $this->reason->record($actor, $reason);

        return DB::transaction(function () use ($actor, $cycle): AwardCycle {
            $locked = $this->rowLock->lock($cycle->id);

            if (! $locked->status->isClosable()) {
                throw IllegalStateTransition::from($locked->status->value, self::VERB);
            }

            $pending = $this->openRequests->of($locked->id);

            if ($pending !== []) {
                throw AwardRejected::requestsPending($pending);
            }

            $previous = $locked->status;
            $locked->forceFill(['status' => AwardCycleStatus::Closed, 'closed_at' => Date::now(), 'closed_from_status' => $previous])->save();
            $this->audit->record(new AuditEntry(AuditEvent::CycleClosed, $locked, ['status' => $previous->value], ['status' => AwardCycleStatus::Closed->value]));

            CycleClosed::dispatch($locked, $actor->id);

            return $locked;
        });
    }
}
