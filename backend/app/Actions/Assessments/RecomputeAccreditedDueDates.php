<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Audit\AuditEvent;
use App\Models\Assessments\Assessment;
use App\Services\Assessments\AccreditedReviewSla;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityDeadlineFreezer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class RecomputeAccreditedDueDates
{
    private const int CHUNK = 100;

    private const string DUE_AT = 'due_at';

    private const int SECONDS_PER_DAY = 86400;

    public function __construct(
        private AccreditedReviewSla $sla,
        private AssessmentRowLock $rowLock,
        private EntityDeadlineFreezer $deadlines,
        private AuditRecorder $audit,
    ) {}

    public function handle(): int
    {
        $days = $this->sla->days();
        $recomputed = 0;

        Assessment::query()->select('id')->ofPath(AssessmentPath::Accredited)->open()->whereNotNull(self::DUE_AT)->where('review_sla_days', '<>', $days)
            ->chunkById(self::CHUNK, function (Collection $rows) use ($days, &$recomputed): void {
                foreach ($rows as $row) {
                    $recomputed += $this->recompute($row->id, $days) ? 1 : 0;
                }
            });

        return $recomputed;
    }

    private function recompute(int $assessmentId, int $days): bool
    {
        return DB::transaction(function () use ($assessmentId, $days): bool {
            $locked = $this->rowLock->lock($assessmentId);
            $before = $locked->due_at;

            if (! $before instanceof Carbon || $locked->review_sla_days === null || $locked->review_sla_days === $days || ! $locked->isOpen()) {
                return false;
            }

            $shiftSeconds = ($days - $locked->review_sla_days) * self::SECONDS_PER_DAY;
            $after = $before->copy()->addSeconds($shiftSeconds);
            $locked->forceFill([
                self::DUE_AT => $after,
                'review_sla_days' => $days,
                'overdue_notified_at' => $after->greaterThan(Date::now()) ? null : $locked->overdue_notified_at,
            ])->save();
            $this->deadlines->shift($locked, self::DUE_AT, $shiftSeconds);

            $this->audit->record(new AuditEntry(AuditEvent::AssessmentDueDatesRecomputed, $locked, [self::DUE_AT => $before->toIso8601String()], [
                self::DUE_AT => $after->toIso8601String(),
                'review_sla_days' => $days,
            ]));

            return true;
        });
    }
}
