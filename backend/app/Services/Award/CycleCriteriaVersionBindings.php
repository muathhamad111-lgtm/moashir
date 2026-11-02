<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Contracts\Criteria\CycleCriteriaBindings;
use App\Data\Audit\AuditEntry;
use App\Data\Criteria\CycleBinding;
use App\Data\Criteria\CycleBindingSummary;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Models\Award\AwardCycle;
use App\Models\Criteria\CriteriaVersion;
use App\Services\Audit\AuditRecorder;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final readonly class CycleCriteriaVersionBindings implements CycleCriteriaBindings
{
    public function __construct(private AuditRecorder $audit) {}

    public function summary(): CycleBindingSummary
    {
        $running = AwardCycle::query()->running()->withCount([
            'requests as open_requests_count' => static fn (Builder $request): Builder => $request->whereIn('status', AssessmentPath::Advanced->openStateValues()),
        ])->first();
        $drafts = AwardCycle::query()->where('status', AwardCycleStatus::Draft)->orderBy('id')->get();

        return new CycleBindingSummary(
            $running instanceof AwardCycle ? $this->bindingOf($running, ScalarValue::int($running->getAttribute('open_requests_count'))) : null,
            array_values($drafts->map(fn (AwardCycle $draft): CycleBinding => $this->bindingOf($draft, 0))->all()),
        );
    }

    public function moveDrafts(CriteriaVersion $to): array
    {
        $moved = [];
        $drafts = AwardCycle::query()->where('status', AwardCycleStatus::Draft)->where('criteria_version_id', '<>', $to->id)->orderBy('id')->lockForUpdate()->get();

        foreach ($drafts as $draft) {
            $from = $draft->criteria_version_id;
            $draft->forceFill(['criteria_version_id' => $to->id])->save();
            $this->audit->record(new AuditEntry(AuditEvent::CycleCriteriaVersionMoved, $draft, ['criteria_version_id' => $from], ['criteria_version_id' => $to->id]));
            $moved[] = $draft->id;
        }

        return $moved;
    }

    private function bindingOf(AwardCycle $cycle, int $openRequests): CycleBinding
    {
        return new CycleBinding($cycle->id, $cycle->name, $cycle->status->value, $cycle->criteria_version_id, $openRequests);
    }
}
