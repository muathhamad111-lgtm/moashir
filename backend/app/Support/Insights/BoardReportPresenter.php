<?php

declare(strict_types=1);

namespace App\Support\Insights;

use App\Data\Insights\BandedMain;
use App\Data\Insights\ExecutiveSummary;
use App\Data\Insights\QuotedItem;
use App\Enums\Certificates\CertificateLevel;
use App\Models\Assessments\AssessmentSummary;
use App\Support\HijriDate;
use App\Support\ValueObjects\LocalizedText;
use Carbon\CarbonInterface;

final readonly class BoardReportPresenter
{
    public function __construct(private int $quoteLimit, private int $summaryLimit, private string $timezone) {}

    /**
     * @return array<string, mixed>
     */
    public function present(ExecutiveSummary $summary, CarbonInterface $generatedAt): array
    {
        $result = $summary->subject->result;
        $level = ExecutiveSummaryPresenter::levelOf($summary);
        $verifiedAt = $result->verifiedAt()?->copy()->setTimezone($this->timezone);
        $generatedAt = $generatedAt->copy()->setTimezone($this->timezone);

        return [
            ...ExecutiveSummaryPresenter::present($summary),
            'version_label' => $summary->subject->tree->versionLabel?->toArray(),
            'level_label' => $level instanceof CertificateLevel ? LocalizedText::fromTranslation('certificates.levels.'.$level->value)->toArray() : null,
            'verified_on' => $verifiedAt?->toDateString(),
            'verified_on_hijri' => $verifiedAt === null ? null : $this->hijri($verifiedAt),
            'generated_on' => $generatedAt->toDateString(),
            'generated_on_hijri' => $this->hijri($generatedAt),
            'generated_at' => $generatedAt->format('H:i'),
            'assessor_summary_excerpt' => $this->summaryExcerptOf($summary),
            'strong_areas' => array_map($this->quoted(...), $summary->highlights->strongAreas),
            'priority_gaps' => array_map($this->quoted(...), $summary->highlights->priorityGaps),
            'bars' => array_map(static fn (BandedMain $main): array => [
                'code' => $main->main->code,
                'name' => $main->main->name?->toArray(),
                'percent' => $main->main->figures->percent->toDisplay(),
                'band' => $main->band->value,
            ], $summary->profile->mains),
        ];
    }

    /**
     * @return array{text: string, truncated: bool}|null
     */
    private function summaryExcerptOf(ExecutiveSummary $summary): ?array
    {
        if (! $summary->assessorSummary instanceof AssessmentSummary) {
            return null;
        }

        $excerpt = TextExcerpt::of($summary->assessorSummary->body, $this->summaryLimit);

        return ['text' => $excerpt->text, 'truncated' => $excerpt->isTruncated];
    }

    /**
     * @return array<string, mixed>
     */
    private function quoted(QuotedItem $quoted): array
    {
        $excerpt = $quoted->quote === null ? null : TextExcerpt::of($quoted->quote, $this->quoteLimit);

        return [
            ...StrengthsGapsPresenter::item($quoted->item),
            'quote' => $excerpt instanceof TextExcerpt ? $excerpt->text.($excerpt->isTruncated ? '…' : '') : null,
        ];
    }

    /**
     * @return array{ar: string, en: string}
     */
    private function hijri(CarbonInterface $date): array
    {
        return ['ar' => HijriDate::format($date, 'ar', $this->timezone), 'en' => HijriDate::format($date, 'en', $this->timezone)];
    }
}
