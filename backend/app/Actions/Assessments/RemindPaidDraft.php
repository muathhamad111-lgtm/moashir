<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\PaidDraftReminder;
use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\PaidDraftReminded;
use App\Exceptions\Assessments\PaidDraftReminderRejected;
use App\Models\Assessments\Assessment;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Audit\AuditRecorder;
use App\Support\Assessments\PaidDraftReminderWindow;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class RemindPaidDraft
{
    public function __construct(private AssessmentRowLock $rows, private AuditRecorder $audit) {}

    public function handle(Assessment $assessment): PaidDraftReminder
    {
        return DB::transaction(function () use ($assessment): PaidDraftReminder {
            $locked = $this->rows->lock($assessment->id);
            $now = Date::now();

            if (! PaidDraftReminderWindow::isPaidDraft($locked)) {
                throw PaidDraftReminderRejected::notPaidDraft();
            }

            $next = PaidDraftReminderWindow::nextRemindAt($locked);

            if ($next instanceof CarbonInterface && $now->lessThan($next)) {
                throw PaidDraftReminderRejected::tooSoon($next->toIso8601String());
            }

            $locked->forceFill(['paid_draft_reminded_at' => $now])->save();
            $this->audit->record(new AuditEntry(AuditEvent::RequestReminderSent, $locked, [], ['request_number' => $locked->request_number]));
            PaidDraftReminded::dispatch($locked);

            return new PaidDraftReminder($locked->id, $now, $now->copy()->addHours(PaidDraftReminderWindow::HOURS_BETWEEN_REMINDERS));
        });
    }
}
