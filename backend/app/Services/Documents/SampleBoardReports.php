<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Data\Documents\PdfDocument;
use App\Enums\Documents\DocumentTemplate;
use App\Enums\Documents\DocumentWatermark;
use App\Support\HijriDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;

final readonly class SampleBoardReports
{
    private const array MAINS = [
        ['M1', 'القيادة والحوكمة', 'Leadership and governance', '88.00', 'leading'],
        ['M2', 'التخطيط الاستراتيجي', 'Strategic planning', '74.50', 'mature'],
        ['M3', 'الموارد البشرية والتطوع', 'Human resources and volunteering', '66.25', 'developing'],
        ['M4', 'الموارد المالية والاستدامة', 'Financial resources and sustainability', '91.00', 'leading'],
        ['M5', 'البرامج والمشاريع', 'Programmes and projects', '58.75', 'developing'],
        ['M6', 'الشراكات وأصحاب العلاقة', 'Partnerships and stakeholders', '81.00', 'leading'],
        ['M7', 'التحول الرقمي والابتكار', 'Digital transformation and innovation', '47.50', 'emerging'],
        ['M8', 'قياس الأثر والنتائج', 'Impact measurement and results', '52.00', 'developing'],
        ['M9', 'إدارة المخاطر والامتثال', 'Risk management and compliance', '77.00', 'mature'],
        ['M10', 'الاتصال المؤسسي', 'Institutional communication', '69.99', 'developing'],
        ['M11', 'خدمة المستفيدين', 'Beneficiary services', '84.25', 'leading'],
        ['M12', 'إدارة المعرفة', 'Knowledge management', '61.00', 'developing'],
    ];

    public function __construct(private Repository $config, private DocumentAssets $assets) {}

    /**
     * @return array<string, PdfDocument>
     */
    public function all(): array
    {
        return [
            'sample-board-report-ar.pdf' => $this->boardReport('ar'),
            'sample-board-report-en.pdf' => $this->boardReport('en'),
        ];
    }

    private function boardReport(string $locale): PdfDocument
    {
        $now = CarbonImmutable::now($this->config->string('app.display_timezone'));
        $quote = str_repeat('توجد سياسة معتمدة ومطبّقة مع شواهد موثقة ومتابعة دورية من الإدارة التنفيذية. ', 3);

        return new PdfDocument(DocumentTemplate::BoardReport->value, [
            'result' => ['number' => 'ACC-'.$now->year.'-000045', 'snapshot_version' => 1],
            'entity' => ['name' => ['ar' => 'جمعية الأمل الخيرية للتنمية الأسرية', 'en' => 'Al-Amal Family Development Charity']],
            'entity_logo' => $this->assets->sampleEntityLogo(),
            'version_label' => ['ar' => 'نموذج التميز 2026', 'en' => 'Excellence model 2026'],
            'verified_on' => $now->toDateString(),
            'verified_on_hijri' => $this->hijri($now),
            'generated_on' => $now->toDateString(),
            'generated_on_hijri' => $this->hijri($now),
            'generated_at' => $now->format('H:i'),
            'score' => ['percent' => '71.12', 'earned' => '711.20', 'total_weight' => '1000.00'],
            'certificate' => ['standing' => 'level', 'level' => 'silver'],
            'level_label' => ['ar' => trans('certificates.levels.silver', [], 'ar'), 'en' => trans('certificates.levels.silver', [], 'en')],
            'change' => ['delta_pp' => '6.40', 'previous' => ['number' => 'ACC-'.($now->year - 1).'-000012'], 'versions_differ' => false],
            'assessor_summary_excerpt' => ['text' => mb_substr(str_repeat('أداء الجهة متوازن في الحوكمة والإدارة المالية، مع حاجة واضحة لتطوير قياس الأثر والتحول الرقمي. ', 7), 0, 590), 'truncated' => true],
            'strong_areas' => array_map(fn (int $index): array => $this->item($index, $quote), [3, 0, 10]),
            'priority_gaps' => array_map(fn (int $index): array => $this->item($index, $quote), [6, 7, 4]),
            'profile' => ['classes' => ['strength' => 9, 'opportunity' => 21, 'critical_gap' => 4], 'bands' => ['emerging' => 1, 'developing' => 5, 'mature' => 2, 'leading' => 4]],
            'bars' => array_map(static fn (array $main): array => ['code' => $main[0], 'name' => ['ar' => $main[1], 'en' => $main[2]], 'percent' => $main[3], 'band' => $main[4]], self::MAINS),
            'rules' => ['gap_thresholds_version' => 1, 'maturity_bands_version' => 1],
        ], DocumentWatermark::Test->value, $locale);
    }

    /**
     * @return array<string, mixed>
     */
    private function item(int $index, string $quote): array
    {
        [$code, $nameAr, $nameEn, $percent] = self::MAINS[$index];

        return ['sub_code' => $code.'.1', 'sub_name' => ['ar' => $nameAr, 'en' => $nameEn], 'percent' => $percent, 'quote' => mb_substr($quote, 0, 199).'…'];
    }

    /**
     * @return array{ar: string, en: string}
     */
    private function hijri(CarbonImmutable $date): array
    {
        $timezone = $this->config->string('app.display_timezone');

        return ['ar' => HijriDate::format($date, 'ar', $timezone), 'en' => HijriDate::format($date, 'en', $timezone)];
    }
}
