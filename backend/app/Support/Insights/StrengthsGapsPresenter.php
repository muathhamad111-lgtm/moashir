<?php

declare(strict_types=1);

namespace App\Support\Insights;

use App\Data\Assessments\FeedbackSheet;
use App\Data\Insights\ClassifiedMain;
use App\Data\Insights\ClassifiedSub;
use App\Data\Insights\StrengthsGapsReport;
use App\Enums\Assessments\FeedbackKind;

final class StrengthsGapsPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(StrengthsGapsReport $report): array
    {
        $classes = $report->classes;
        $feedback = $report->subject->feedback;

        return [
            'result' => ResultRefPresenter::present($report->subject->result),
            'rules' => [...$classes->rules->toArray(), 'effective_since' => $report->subject->rules->effectiveSince?->toIso8601String()],
            'counts' => $classes->counts(),
            'mains' => array_map(static fn (ClassifiedMain $main): array => [
                'code' => $main->main->code,
                'name' => $main->main->name?->toArray(),
                'percent' => $main->main->figures->percent->toDisplay(),
                'class' => $main->class->value,
            ], $classes->mains),
            'items' => [
                'strengths' => self::items($classes->strengths, $feedback),
                'opportunities' => self::items($classes->opportunities, $feedback),
                'critical_gaps' => self::items($classes->criticalGaps, $feedback),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function item(ClassifiedSub $item): array
    {
        $figures = $item->sub->figures;

        return [
            'main_code' => $item->main->code,
            'main_name' => $item->main->name?->toArray(),
            'sub_code' => $item->sub->code,
            'sub_name' => $item->sub->name?->toArray(),
            'weight' => $figures->weight,
            'earned' => $figures->earned,
            'percent' => $figures->percent->toDisplay(),
            'lost_points' => $figures->lostPoints(),
            'class' => $item->class->value,
        ];
    }

    /**
     * @param  list<ClassifiedSub>  $items
     * @return list<array<string, mixed>>
     */
    private static function items(array $items, FeedbackSheet $feedback): array
    {
        return array_map(static fn (ClassifiedSub $item): array => [
            ...self::item($item),
            'assessor' => [
                'strengths' => $feedback->bodyOf($item->sub->code, FeedbackKind::Strength),
                'improvement_points' => $feedback->bodyOf($item->sub->code, FeedbackKind::Improvement),
            ],
        ], $items);
    }
}
