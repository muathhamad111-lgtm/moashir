<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Events\Award\SubmissionDeadlineApproaching;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardCycleReminder;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityFreezeGuard;
use App\Support\ScalarValue;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class SendSubmissionDeadlineReminders
{
    private const int SECONDS_PER_DAY = 86_400;

    public function __construct(private Repository $config, private EntityFreezeGuard $freezeGuard, private AuditRecorder $audit) {}

    public function handle(): int
    {
        $now = Date::now();
        $sent = 0;

        foreach (AwardCycle::query()->where('status', AwardCycleStatus::SubmissionOpen)->where('submission_end', '>', $now)->orderBy('id')->get() as $cycle) {
            $sent += $this->remind($cycle, $now);
        }

        return $sent;
    }

    private function remind(AwardCycle $cycle, CarbonInterface $now): int
    {
        $due = $this->dueThresholds($cycle, $now);

        if ($due === []) {
            return 0;
        }

        return DB::transaction(function () use ($cycle, $due): int {
            $closest = min($due);
            $draftIds = $this->draftIdsOf($cycle);

            foreach ($due as $days) {
                AwardCycleReminder::query()->create([
                    'award_cycle_id' => $cycle->id,
                    'days_before' => $days,
                    'recipients_count' => $days === $closest ? count($draftIds) : 0,
                    'sent_at' => Date::now(),
                ]);
            }

            $this->audit->record(new AuditEntry(AuditEvent::CycleRemindersSent, $cycle, [], ['days_before' => $closest, 'draft_count' => count($draftIds)]));
            SubmissionDeadlineApproaching::dispatch($cycle, $closest, $draftIds);

            return count($draftIds);
        });
    }

    /**
     * @return list<int>
     */
    private function dueThresholds(AwardCycle $cycle, CarbonInterface $now): array
    {
        $secondsLeft = (int) $now->diffInSeconds($cycle->submission_end, true);
        $sent = AwardCycleReminder::query()->where('award_cycle_id', $cycle->id)->pluck('days_before')->map(static fn (mixed $days): int => ScalarValue::int($days))->all();

        return array_values(array_filter(
            $this->thresholds(),
            static fn (int $days): bool => $secondsLeft <= $days * self::SECONDS_PER_DAY && ! in_array($days, $sent, true),
        ));
    }

    /**
     * @return list<int>
     */
    private function draftIdsOf(AwardCycle $cycle): array
    {
        return array_values($this->freezeGuard->excludeFrozen(Assessment::query()->inCycle($cycle->id)->where('status', AdvancedAssessmentStatus::Draft))
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => ScalarValue::int($id))
            ->all());
    }

    /**
     * @return list<int>
     */
    private function thresholds(): array
    {
        $configured = $this->config->get('moashir.award.submission_reminder_days', [7, 3, 1]);

        return array_values(array_map(static fn (mixed $days): int => ScalarValue::int($days), is_array($configured) ? $configured : []));
    }
}
