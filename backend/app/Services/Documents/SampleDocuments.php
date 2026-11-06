<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Data\Documents\PdfDocument;
use App\Enums\Documents\DocumentTemplate;
use App\Enums\Documents\DocumentWatermark;
use App\Support\HijriDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;

final readonly class SampleDocuments
{
    private const string ENTITY_AR = 'جمعية الأمل الخيرية للتنمية الأسرية';

    private const string ENTITY_EN = 'Al-Amal Family Development Charity';

    private const array CRITERIA = [
        ['M1', 'القيادة والحوكمة', 'Leadership and governance', '150.00', '132.00', '88.00'],
        ['M2', 'التخطيط الاستراتيجي', 'Strategic planning', '100.00', '85.00', '85.00'],
        ['M3', 'الموارد البشرية والتطوع', 'Human resources and volunteering', '150.00', '127.50', '85.00'],
        ['M4', 'الموارد المالية والاستدامة', 'Financial resources and sustainability', '150.00', '135.00', '90.00'],
        ['M5', 'البرامج والمشاريع', 'Programmes and projects', '150.00', '129.00', '86.00'],
        ['M6', 'الشراكات وأصحاب العلاقة', 'Partnerships and stakeholders', '100.00', '90.00', '90.00'],
        ['M7', 'التحول الرقمي والابتكار', 'Digital transformation and innovation', '100.00', '84.00', '84.00'],
        ['M8', 'قياس الأثر والنتائج', 'Impact measurement and results', '100.00', '92.50', '92.50'],
    ];

    public function __construct(private Repository $config, private DocumentAssets $assets, private SampleBoardReports $boardReports) {}

    /**
     * @return array<string, PdfDocument>
     */
    public function all(): array
    {
        return [
            'sample-certificate-gold.pdf' => $this->certificate('gold', '87.50', $this->assets->sampleEntityLogo()),
            'sample-certificate-silver.pdf' => $this->certificate('silver', '74.20', null),
            'sample-certificate-participation.pdf' => $this->certificate(null, '54.25', null),
            'sample-feedback-report-ar.pdf' => $this->feedbackReport('ar'),
            'sample-feedback-report-en.pdf' => $this->feedbackReport('en'),
            'sample-receipt.pdf' => $this->receipt(),
            ...$this->boardReports->all(),
        ];
    }

    private function certificate(?string $level, string $percent, ?string $entityLogo): PdfDocument
    {
        $issuedAt = $this->issuedAt();

        return new PdfDocument(DocumentTemplate::AccreditedCertificate->value, [
            'number' => 'CRT-'.$issuedAt->year.'-000123',
            'verification_code' => 'K7Q4-9XPM-2DHT-R8WN-5C3J-V6BA-QE4Z',
            'verification_url' => rtrim($this->config->string('moashir.frontend_url'), '/').'/verify/K7Q49XPM2DHTR8WN5C3JV6BAQE4Z',
            'request_number' => 'ACC-'.$issuedAt->year.'-000045',
            'entity_name' => ['ar' => self::ENTITY_AR, 'en' => self::ENTITY_EN],
            'entity_logo' => $entityLogo,
            'percent' => $percent,
            'level' => $level,
            'level_label' => $level === null ? null : ['ar' => trans('certificates.levels.'.$level, [], 'ar'), 'en' => trans('certificates.levels.'.$level, [], 'en')],
            'issued_on' => $issuedAt->toDateString(),
            'issued_on_hijri' => $this->hijri($issuedAt),
            'issue_year' => (string) $issuedAt->year,
            'is_test' => true,
        ], DocumentWatermark::Test->value);
    }

    private function feedbackReport(string $locale): PdfDocument
    {
        $completedAt = $this->issuedAt();

        return new PdfDocument(DocumentTemplate::FeedbackReport->value, [
            'number' => 'ACC-'.$completedAt->year.'-000045',
            'path_label' => ['ar' => trans('assessments.paths.accredited', [], 'ar'), 'en' => trans('assessments.paths.accredited', [], 'en')],
            'entity' => [
                'name' => ['ar' => self::ENTITY_AR, 'en' => self::ENTITY_EN],
                'reference' => 'ENT-000017',
                'type_label' => ['ar' => trans('entities.types.civil_association', [], 'ar'), 'en' => trans('entities.types.civil_association', [], 'en')],
                'region' => ['ar' => 'منطقة الرياض', 'en' => 'Riyadh Region'],
                'city' => ['ar' => 'الرياض', 'en' => 'Riyadh'],
            ],
            'completed_on' => $completedAt->toDateString(),
            'completed_on_hijri' => $this->hijri($completedAt),
            'generated_on' => $completedAt->toDateString(),
            'generated_on_hijri' => $this->hijri($completedAt),
            'total' => '875.00',
            'max_total' => '1000.00',
            'percent' => '87.50',
            'certificate' => [
                'number' => 'CRT-'.$completedAt->year.'-000123',
                'level' => 'gold',
                'level_label' => ['ar' => trans('certificates.levels.gold', [], 'ar'), 'en' => trans('certificates.levels.gold', [], 'en')],
            ],
            'criteria' => array_map($this->criterion(...), self::CRITERIA),
        ], DocumentWatermark::Test->value, $locale);
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}  $row
     * @return array<string, mixed>
     */
    private function criterion(array $row): array
    {
        [$code, $nameAr, $nameEn, $weight, $earned, $percent] = $row;

        return [
            'code' => $code,
            'name' => ['ar' => $nameAr, 'en' => $nameEn],
            'weight' => $weight,
            'earned' => $earned,
            'percent' => $percent,
            'subs' => [
                [
                    'code' => $code.'.1',
                    'name' => ['ar' => 'السياسات والإجراءات المعتمدة في '.$nameAr, 'en' => 'Approved policies and procedures in '.mb_strtolower($nameEn)],
                    'weight' => $weight,
                    'earned' => $earned,
                    'percent' => $percent,
                    'strengths' => 'توجد سياسة معتمدة ومحدثة لعام 2026 مع شواهد موثقة (3 ملفات PDF).',
                    'improvement_points' => 'يوصى بإعداد خطة تحسين واضحة خلال 90 يومًا وربطها بمؤشرات الأداء (KPIs).',
                    'notes' => null,
                ],
            ],
        ];
    }

    private function receipt(): PdfDocument
    {
        $issuedAt = $this->issuedAt();

        return new PdfDocument(DocumentTemplate::PaymentReceipt->value, [
            'payment_reference' => 'PAY-'.$issuedAt->year.'-000031',
            'entity_reference' => 'ENT-000017',
            'entity_name' => ['ar' => self::ENTITY_AR, 'en' => self::ENTITY_EN],
            'purpose' => 'accredited',
            'price_before_vat' => '1500.00',
            'vat_rate' => '15.00',
            'vat_amount' => '225.00',
            'total' => '1725.00',
            'currency' => 'SAR',
            'currency_label' => ['ar' => trans('finance.currencies.SAR', [], 'ar'), 'en' => trans('finance.currencies.SAR', [], 'en')],
            'method' => 'mada',
            'method_label' => ['ar' => trans('finance.methods.mada', [], 'ar'), 'en' => trans('finance.methods.mada', [], 'en')],
            'card_last4' => '4242',
            'vat_registration_number' => '300000000000003',
            'paid_on' => $issuedAt->toDateString(),
            'number' => null,
            'is_test' => true,
            'status' => 'issued',
            'issued_on' => $issuedAt->toDateString(),
            'issued_on_hijri' => $this->hijri($issuedAt),
            'purpose_label' => ['ar' => trans('finance.purposes.accredited', [], 'ar'), 'en' => trans('finance.purposes.accredited', [], 'en')],
            'labels' => ['ar' => trans('finance.receipt', [], 'ar'), 'en' => trans('finance.receipt', [], 'en')],
        ], DocumentWatermark::Test->value);
    }

    private function issuedAt(): CarbonImmutable
    {
        return CarbonImmutable::now($this->config->string('app.display_timezone'));
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
