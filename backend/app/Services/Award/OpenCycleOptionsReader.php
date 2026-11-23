<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Award\AwardEntryStanding;
use App\Data\Award\OpenCycleOption;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Award\AwardWindowState;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Services\Paths\PathPricingReader;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

final readonly class OpenCycleOptionsReader
{
    public function __construct(
        private CurrentCycleReader $cycles,
        private PathPricingReader $pricing,
        private AwardIneligibilityResolver $ineligibility,
    ) {}

    /**
     * @return list<OpenCycleOption>
     */
    public function forEntity(Entity $entity): array
    {
        $cycles = $this->cycles->openForSubmission();

        if ($cycles->isEmpty()) {
            return [];
        }

        $cycleIds = array_values($cycles->map(static fn (AwardCycle $cycle): int => $cycle->id)->all());
        $requests = $this->requestsIn($entity, $cycleIds);
        $orders = $this->openOrdersIn($entity, $cycleIds);
        $pricing = $this->pricing->of(PaymentPurpose::Award);
        $now = Date::now();

        return array_values($cycles->map(function (AwardCycle $cycle) use ($entity, $requests, $orders, $pricing, $now): OpenCycleOption {
            $request = $requests->get($cycle->id);
            $standing = new AwardEntryStanding(AwardWindowState::Open, $cycle, $request, $orders->get($cycle->id), $pricing);
            $ineligibility = $this->ineligibility->resolve($entity, $standing, $now);

            return new OpenCycleOption($cycle, $this->pricing->quoteOf($cycle->entryFee()), $ineligibility, $request);
        })->all());
    }

    /**
     * @param  list<int>  $cycleIds
     * @return Collection<int, Assessment>
     */
    private function requestsIn(Entity $entity, array $cycleIds): Collection
    {
        return Assessment::query()
            ->ownedBy($entity->id)
            ->where('path', AssessmentPath::Advanced)
            ->whereIn('award_cycle_id', $cycleIds)
            ->orderByRaw('case when status = ? then 1 else 0 end', [AdvancedAssessmentStatus::RejectedConditions->value])
            ->latest('id')
            ->get()
            ->unique('award_cycle_id')
            ->keyBy('award_cycle_id');
    }

    /**
     * @param  list<int>  $cycleIds
     * @return Collection<int, Payment>
     */
    private function openOrdersIn(Entity $entity, array $cycleIds): Collection
    {
        return Payment::query()
            ->ownedBy($entity->id)
            ->where('purpose', PaymentPurpose::Award)
            ->whereIn('award_cycle_id', $cycleIds)
            ->open()
            ->latest('id')
            ->get()
            ->unique('award_cycle_id')
            ->keyBy('award_cycle_id');
    }
}
