<?php

declare(strict_types=1);

namespace App\Support\Insights;

use App\Data\Insights\ExecutiveSummary;
use App\Data\Insights\ExecutiveSummaryView;
use App\Data\Insights\QuotedItem;
use App\Enums\Certificates\CertificateLevel;
use App\Enums\Insights\CertificateStanding;
use App\Models\Assessments\AssessmentSummary;
use App\Models\Certificates\Certificate;
use App\Models\Entities\Entity;

final class ExecutiveSummaryPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(ExecutiveSummary $summary): array
    {
        $subject = $summary->subject;
        $assessment = $subject->result->assessment;
        $total = $subject->tree->total;

        return [
            'result' => ResultRefPresenter::present($subject->result),
            'entity' => self::entityOf($assessment->entity),
            'score' => ['percent' => $total->percent->toDisplay(), 'earned' => $total->earned, 'total_weight' => $total->weight],
            'certificate' => [
                'standing' => $summary->standing->value,
                'level' => self::levelOf($summary)?->value,
            ],
            'change' => [
                'delta_pp' => $summary->delta->total?->toDisplay(),
                'previous' => ResultRefPresenter::presentOptional($subject->previous),
                'versions_differ' => $summary->delta->versionsDiffer,
            ],
            'assessor_summary' => $summary->assessorSummary instanceof AssessmentSummary ? ['body' => $summary->assessorSummary->body, 'version' => $summary->assessorSummary->version] : null,
            'profile' => ['classes' => $summary->classes->counts(), 'bands' => $summary->profile->mainCounts()],
            'strong_areas' => array_map(self::quoted(...), $summary->highlights->strongAreas),
            'priority_gaps' => array_map(self::quoted(...), $summary->highlights->priorityGaps),
            'rules' => [
                ...ResultRefPresenter::rules($subject->rules),
                'gap_thresholds' => $subject->rules->gaps->toArray(),
                'maturity_bands' => $subject->rules->bands->toArray(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function presentView(ExecutiveSummaryView $view): array
    {
        return [
            ...self::present($view->summary),
            'entity' => [...self::entityOf($view->summary->subject->result->assessment->entity), 'logo_url' => $view->logoUrl],
        ];
    }

    public static function levelOf(ExecutiveSummary $summary): ?CertificateLevel
    {
        $certificate = $summary->subject->result->certificate;

        return $summary->standing === CertificateStanding::Level && $certificate instanceof Certificate ? $certificate->level : null;
    }

    /**
     * @return array{name: array{ar: string, en: string|null}, has_logo: bool}
     */
    private static function entityOf(Entity $entity): array
    {
        return ['name' => $entity->name->toArray(), 'has_logo' => $entity->logo_file_id !== null];
    }

    /**
     * @return array<string, mixed>
     */
    private static function quoted(QuotedItem $quoted): array
    {
        return [...StrengthsGapsPresenter::item($quoted->item), 'quote' => $quoted->quote];
    }
}
