<?php

declare(strict_types=1);

namespace App\Support\Reports;

use App\Data\Assessments\CriteriaNodeNames;
use App\Data\Assessments\FeedbackSheet;
use App\Data\Reports\FeedbackReport;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Models\Certificates\Certificate;
use App\Support\Assessments\PathPresenter;
use App\Support\Assessments\ResultBreakdownPresenter;
use App\Support\HijriDate;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Support\Carbon;

final class FeedbackReportPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(FeedbackReport $report, string $timezone): array
    {
        $view = $report->result;
        $assessment = $view->assessment;
        $identity = [
            'assessment_id' => $assessment->id,
            'number' => $assessment->request_number,
            ...PathPresenter::of($assessment->path),
            'status' => $assessment->status->value,
            'is_frozen' => $assessment->isFrozen(),
        ];

        if (! $view->snapshot instanceof AssessmentResultSnapshot || ! $view->names instanceof CriteriaNodeNames) {
            return [...$identity, 'available' => false];
        }

        $completedAt = ($assessment->completed_at ?? $view->snapshot->created_at)->copy()->setTimezone($timezone);

        return [
            ...$identity,
            'available' => true,
            'source' => 'assessor_assessment',
            'result_version' => $view->snapshot->version,
            'entity' => self::entityOf($report),
            'completed_at' => $assessment->completed_at?->toIso8601String(),
            'completed_on' => $completedAt->toDateString(),
            'completed_on_hijri' => self::hijri($completedAt, $timezone),
            'total' => $view->snapshot->total_score->toDisplay(),
            'max_total' => $view->snapshot->total_weight->toDecimal(),
            'percent' => $view->snapshot->percent->toDisplay(),
            'certificate' => self::certificateOf($report->certificate),
            'criteria' => self::criteriaOf(new ResultBreakdownPresenter($view->snapshot->breakdown, $view->names), $view->feedback ?? new FeedbackSheet([])),
        ];
    }

    /**
     * @return array{ar: string, en: string}
     */
    public static function hijri(Carbon $date, string $timezone): array
    {
        return ['ar' => HijriDate::format($date, 'ar', $timezone), 'en' => HijriDate::format($date, 'en', $timezone)];
    }

    /**
     * @return array<string, mixed>
     */
    private static function entityOf(FeedbackReport $report): array
    {
        $entity = $report->result->assessment->entity;

        return [
            'id' => $entity->id,
            'reference' => $entity->reference(),
            'name' => $entity->name->toArray(),
            'type' => $entity->entity_type->value,
            'type_label' => LocalizedText::fromTranslation('entities.types.'.$entity->entity_type->value)->toArray(),
            'region' => $entity->region->name->toArray(),
            'city' => $entity->city->name->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function certificateOf(?Certificate $certificate): ?array
    {
        if (! $certificate instanceof Certificate) {
            return null;
        }

        return [
            'id' => $certificate->public_id,
            'number' => $certificate->number,
            'level' => $certificate->level?->value,
            'level_label' => $certificate->level === null ? null : LocalizedText::fromTranslation('certificates.levels.'.$certificate->level->value)->toArray(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function criteriaOf(ResultBreakdownPresenter $breakdown, FeedbackSheet $feedback): array
    {
        $subsByMain = [];

        foreach ($breakdown->subs() as $sub) {
            $subsByMain[ScalarValue::int($sub['main_criterion_id'] ?? null)][] = [
                'sub_id' => $sub['sub_id'],
                'code' => $sub['code'],
                'name' => $sub['name'],
                'weight' => $sub['weight'],
                'earned' => $sub['earned'],
                'percent' => $sub['percent'],
                ...$feedback->fieldsOf(ScalarValue::string($sub['code'] ?? null)),
            ];
        }

        return array_map(static fn (array $main): array => [
            ...$main,
            'subs' => $subsByMain[ScalarValue::int($main['criterion_id'] ?? null)] ?? [],
        ], $breakdown->mains());
    }
}
