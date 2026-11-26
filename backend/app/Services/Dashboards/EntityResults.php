<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Criteria\MainCriterion;
use App\Services\Insights\AccreditedResultsReader;
use App\Support\Assessments\PathPresenter;
use App\Support\Insights\EligibleResultSelector;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Percent;
use Illuminate\Database\Eloquent\Builder;

final readonly class EntityResults
{
    public function __construct(private AccreditedResultsReader $accredited, private EligibleResultSelector $selector) {}

    public function latestEligibleAccreditedOf(int $entityId): ?Assessment
    {
        return $this->selector->latest($this->accredited->forEntity($entityId))?->assessment;
    }

    public function latestOf(int $entityId): ?Assessment
    {
        return Assessment::query()
            ->ownedBy($entityId)
            ->where(static fn (Builder $visible): Builder => $visible
                ->where(static fn (Builder $self): Builder => $self->where('path', AssessmentPath::SelfAssessment)->where('status', SelfAssessmentStatus::ResultAvailable->value))
                ->orWhere(static fn (Builder $accredited): Builder => $accredited->where('path', AssessmentPath::Accredited)->where('status', AccreditedAssessmentStatus::Completed->value)))
            ->whereNotNull('current_result_snapshot_id')
            ->with('currentResult')
            ->latest('completed_at')
            ->latest('id')
            ->first();
    }

    /**
     * @return array{assessment_id: int, number: string, path: string, path_label: array{ar: string, en: string}, percent: string, completed_at: string|null}|null
     */
    public function summary(?Assessment $assessment): ?array
    {
        $snapshot = $assessment?->currentResult;

        if (! $assessment instanceof Assessment || ! $snapshot instanceof AssessmentResultSnapshot) {
            return null;
        }

        return [
            'assessment_id' => $assessment->id,
            'number' => $assessment->request_number,
            ...PathPresenter::of($assessment->path),
            'percent' => $snapshot->percent->toDisplay(),
            'completed_at' => $assessment->completed_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{code: string, name: array<string, string>|null, percent: string}>
     */
    public function mainsOf(?Assessment $assessment): array
    {
        $snapshot = $assessment?->currentResult;
        $mains = $snapshot instanceof AssessmentResultSnapshot && is_array($snapshot->breakdown['mains'] ?? null) ? $snapshot->breakdown['mains'] : [];

        if ($mains === []) {
            return [];
        }

        $names = MainCriterion::query()->whereKey(array_map(static fn (mixed $main): int => ScalarValue::int(is_array($main) ? ($main['id'] ?? null) : null), $mains))->pluck('name', 'id');

        return array_values(array_map(static function (mixed $main) use ($names): array {
            $values = is_array($main) ? $main : [];
            $name = $names->get(ScalarValue::int($values['id'] ?? null));

            return [
                'code' => ScalarValue::string($values['code'] ?? null),
                'name' => $name instanceof LocalizedText ? $name->toArray() : null,
                'percent' => Percent::of(ScalarValue::string($values['percent'] ?? null, '0'))->toDisplay(),
            ];
        }, $mains));
    }
}
