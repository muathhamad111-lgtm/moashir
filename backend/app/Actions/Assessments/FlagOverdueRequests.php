<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Events\Assessments\RequestOverdue;
use App\Models\Assessments\Assessment;
use App\Services\Assessments\AssessmentRowLock;
use App\Services\Assessments\RequestQueueQuery;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class FlagOverdueRequests
{
    private const int CHUNK = 100;

    public function __construct(
        private RequestQueueQuery $queue,
        private EntityFreezeGuard $freezeGuard,
        private AssessmentRowLock $rowLock,
    ) {}

    public function handle(): int
    {
        $flagged = 0;
        $candidates = $this->freezeGuard->excludeFrozen(Assessment::query()->select('id')->ofPath(AssessmentPath::Accredited)->whereNull('overdue_notified_at'));

        $this->queue->overdue($candidates)->chunkById(self::CHUNK, function (Collection $rows) use (&$flagged): void {
            foreach ($rows as $row) {
                $flagged += $this->flag($row->id) ? 1 : 0;
            }
        });

        return $flagged;
    }

    private function flag(int $assessmentId): bool
    {
        return DB::transaction(function () use ($assessmentId): bool {
            $locked = $this->rowLock->lock($assessmentId);

            if ($locked->overdue_notified_at !== null || ! $locked->isOverdue(Date::now()) || $locked->isFrozen()) {
                return false;
            }

            $locked->forceFill(['overdue_notified_at' => Date::now()])->save();
            RequestOverdue::dispatch($locked, $locked->loadMissing('activeAssignment')->activeAssignment?->assessor_user_id);

            return true;
        });
    }
}
