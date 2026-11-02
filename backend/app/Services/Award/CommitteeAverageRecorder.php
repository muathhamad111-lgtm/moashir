<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\CommitteeTally;
use App\Data\Award\RecordedAverage;
use App\Enums\Audit\AuditEvent;
use App\Models\Assessments\Assessment;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeAverage;
use App\Services\Audit\AuditRecorder;
use App\Support\ScalarValue;
use App\Support\ValueObjects\Percent;

final readonly class CommitteeAverageRecorder
{
    public function __construct(private CommitteeAverageCalculator $calculator, private AuditRecorder $audit) {}

    public function record(Assessment $locked, CommitteeTally $tally, ?int $actorId, bool $isManual): RecordedAverage
    {
        $inputs = $this->inputsOf($tally);
        $digest = hash('sha256', json_encode($inputs, JSON_THROW_ON_ERROR));
        $existing = CommitteeAverage::query()->where('assessment_id', $locked->id)->where('inputs_digest', $digest)->first();
        $recorded = $existing instanceof CommitteeAverage
            ? new RecordedAverage($existing, false)
            : new RecordedAverage($this->create($locked, $inputs, $digest, $actorId, $isManual), true);

        $previousId = $locked->committee_average_id;
        $locked->forceFill(['committee_average_id' => $recorded->average->id])->save();
        $locked->setRelation('committeeAverage', $recorded->average);

        if ($recorded->isNew || $isManual || $previousId !== $recorded->average->id) {
            $this->audit->record(new AuditEntry(AuditEvent::AwardAverageComputed, $locked, ['committee_average_id' => $previousId], [
                'committee_average_id' => $recorded->average->id,
                'version' => $recorded->average->version,
                'percent' => $recorded->average->percent->toDecimal(),
                'member_count' => $recorded->average->member_count,
                'is_new' => $recorded->isNew,
                'is_manual' => $isManual,
            ]));
        }

        return $recorded;
    }

    /**
     * @return list<array{assignment_id: int, percent: string}>
     */
    private function inputsOf(CommitteeTally $tally): array
    {
        return array_values(array_map(static fn (CommitteeAssignment $assignment): array => [
            'assignment_id' => $assignment->id,
            'percent' => ($assignment->percent ?? Percent::zero())->toDecimal(),
        ], $tally->counted));
    }

    /**
     * @param  list<array{assignment_id: int, percent: string}>  $inputs
     */
    private function create(Assessment $locked, array $inputs, string $digest, ?int $actorId, bool $isManual): CommitteeAverage
    {
        $latest = CommitteeAverage::query()->where('assessment_id', $locked->id)->max('version');

        return CommitteeAverage::query()->create([
            'assessment_id' => $locked->id,
            'award_cycle_id' => (int) $locked->award_cycle_id,
            'version' => ScalarValue::int($latest) + 1,
            'percent' => $this->calculator->average(array_map(static fn (array $input): Percent => Percent::of($input['percent']), $inputs)),
            'member_count' => count($inputs),
            'inputs' => $inputs,
            'inputs_digest' => $digest,
            'is_manual' => $isManual,
            'computed_by_user_id' => $actorId,
        ]);
    }
}
