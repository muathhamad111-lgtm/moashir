<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\SelfAssessmentStatus;
use App\Models\Assessments\Assessment;
use App\Services\Entities\EntityFreezeGuard;
use App\Support\Dashboards\AggregateRow;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final readonly class AssessmentTotals
{
    private const array DECIDED_ADVANCED = [
        AdvancedAssessmentStatus::NotQualified,
        AdvancedAssessmentStatus::NonWinner,
        AdvancedAssessmentStatus::NationalWinner,
        AdvancedAssessmentStatus::NationalNonWinner,
    ];

    public function __construct(private EntityFreezeGuard $freezeGuard) {}

    /**
     * @return array<string, int>
     */
    public function overall(CarbonInterface $now): array
    {
        $accreditedQueue = AssessmentPath::Accredited->queueStateValues();
        $advancedQueue = AssessmentPath::Advanced->queueStateValues();
        $openQueue = [...array_intersect($accreditedQueue, AssessmentPath::Accredited->openStateValues())];
        $openAdvanced = [...array_intersect($advancedQueue, AssessmentPath::Advanced->openStateValues())];
        $clock = [...AssessmentPath::Accredited->reviewClockStateValues(), ...AssessmentPath::Advanced->reviewClockStateValues()];

        return AggregateRow::counts($this->operational(), [
            'self_total' => ['path = ?', [AssessmentPath::SelfAssessment->value]],
            'self_completed' => ['path = ? and status = ?', [AssessmentPath::SelfAssessment->value, SelfAssessmentStatus::ResultAvailable->value]],
            'accredited_total' => $this->inPath(AssessmentPath::Accredited, $accreditedQueue),
            'accredited_completed' => ['path = ? and status = ?', [AssessmentPath::Accredited->value, AccreditedAssessmentStatus::Completed->value]],
            'advanced_total' => $this->inPath(AssessmentPath::Advanced, $advancedQueue),
            'advanced_decided' => $this->inPath(AssessmentPath::Advanced, AdvancedAssessmentStatus::valuesOf(...self::DECIDED_ADVANCED)),
            'open_requests' => [
                '((path = ? and status in ('.AggregateRow::placeholders($openQueue).')) or (path = ? and status in ('.AggregateRow::placeholders($openAdvanced).')))',
                [AssessmentPath::Accredited->value, ...array_values($openQueue), AssessmentPath::Advanced->value, ...array_values($openAdvanced)],
            ],
            'overdue' => ['due_at < ? and status in ('.AggregateRow::placeholders($clock).') and path <> ?', [$now->toIso8601String(), ...$clock, AssessmentPath::SelfAssessment->value]],
        ]);
    }

    /**
     * @return Builder<Assessment>
     */
    public function operational(): Builder
    {
        return $this->freezeGuard->excludeFrozen(Assessment::query(), 'assessments.entity_id');
    }

    /**
     * @param  list<string>  $statuses
     * @return array{0: literal-string, 1: list<mixed>}
     */
    private function inPath(AssessmentPath $path, array $statuses): array
    {
        return ['path = ? and status in ('.AggregateRow::placeholders($statuses).')', [$path->value, ...$statuses]];
    }
}
