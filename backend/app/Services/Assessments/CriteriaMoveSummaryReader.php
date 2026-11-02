<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\CriteriaMoveItem;
use App\Data\Assessments\CriteriaMoveSummary;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentCriteriaMove;
use App\Models\Criteria\CriteriaVersion;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedText;

final readonly class CriteriaMoveSummaryReader
{
    public function __construct(private CriteriaItemLabelsReader $labels) {}

    public function latestFor(Assessment $assessment): ?CriteriaMoveSummary
    {
        $move = AssessmentCriteriaMove::query()->where('assessment_id', $assessment->id)->latest('id')->first();

        if (! $move instanceof AssessmentCriteriaMove) {
            return null;
        }

        $currentLabels = $this->labels->in($move->to_version_id, $this->codesOf([...$move->kept, ...$move->added, ...$move->reset]));
        $resetLabels = [...$this->previousLabelsOf($move, $currentLabels), ...$currentLabels];

        return new CriteriaMoveSummary(
            $move,
            $this->versionNumbersOf($move),
            $this->itemsOf($move->kept, $currentLabels),
            $this->itemsOf($move->reset, $resetLabels),
            $this->itemsOf($move->added, $currentLabels),
        );
    }

    /**
     * @param  array<string, LocalizedText>  $currentLabels
     * @return array<string, LocalizedText>
     */
    private function previousLabelsOf(AssessmentCriteriaMove $move, array $currentLabels): array
    {
        $missing = array_values(array_diff($this->codesOf($move->reset), array_keys($currentLabels)));

        return $this->labels->in($move->from_version_id, $missing);
    }

    /**
     * @return array<int, int>
     */
    private function versionNumbersOf(AssessmentCriteriaMove $move): array
    {
        $numbers = [];

        foreach (CriteriaVersion::withTrashed()->whereKey([$move->from_version_id, $move->to_version_id])->get(['id', 'version_number']) as $version) {
            $numbers[$version->id] = $version->version_number;
        }

        return $numbers;
    }

    /**
     * @param  list<array{level: string, code: string}>  $stored
     * @return list<string>
     */
    private function codesOf(array $stored): array
    {
        return array_values(array_unique(array_map(static fn (array $item): string => ScalarValue::string($item['code']), $stored)));
    }

    /**
     * @param  list<array{level: string, code: string}>  $stored
     * @param  array<string, LocalizedText>  $labels
     * @return list<CriteriaMoveItem>
     */
    private function itemsOf(array $stored, array $labels): array
    {
        return array_map(static fn (array $item): CriteriaMoveItem => CriteriaMoveItem::from($item, $labels), $stored);
    }
}
