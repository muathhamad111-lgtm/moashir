<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Services\Assessments\CompletionRequestExpirer;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

final readonly class ExpireCompletionRequests
{
    private const int CHUNK = 100;

    public function __construct(private CompletionRequestExpirer $expirer, private EntityFreezeGuard $freezeGuard) {}

    public function handle(): int
    {
        $expired = 0;

        $this->candidates()->chunkById(self::CHUNK, function (Collection $requests) use (&$expired): void {
            foreach ($requests as $request) {
                $expired += $this->expirer->expire($request->assessment_id) ? 1 : 0;
            }
        });

        return $expired;
    }

    /**
     * @return Builder<AssessmentCompletionRequest>
     */
    private function candidates(): Builder
    {
        return AssessmentCompletionRequest::query()
            ->select(['id', 'assessment_id'])
            ->open()
            ->where('deadline_at', '<', Date::now())
            ->whereHas('assessment', fn (Builder $assessment): Builder => $this->freezeGuard->excludeFrozen($assessment
                ->where(static function (Builder $byPath): void {
                    foreach (AssessmentPath::reviewed() as $path) {
                        $byPath->orWhere(static fn (Builder $inner): Builder => $inner->ofPath($path)->where('status', $path->reviewStages()->needsCompletion->value));
                    }
                })
                ->whereNull('criteria_update_pending_since')));
    }
}
