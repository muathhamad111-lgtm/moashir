<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Exceptions\Console\MailSampleNotRendered;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Support\Facades\Lang;

final class SampleReplacements
{
    public const int SAMPLE_QUANTITY = 3;

    private const array TEXTS = [
        'count' => '3',
        'percent' => '87.50',
        'threshold' => '60.00',
        'average' => '84.25',
        'weight' => '25.00',
        'previous' => '20.00',
        'rank' => '1',
        'version' => '2',
        'code' => '2.3',
        'label' => 'ADV-2026-000088',
        'certificate' => 'CRT-2026-000123',
        'deadline' => '2026-12-15 23:59',
        'submission_end' => '2026-12-15 23:59',
        'committee_end' => '2027-01-20 23:59',
        'provider' => 'Taqnyat',
        'error' => 'timeout',
        'email' => 'info@alamal.example.sa',
        'file' => 'evidence-2-3.pdf',
        'ending' => '7519',
    ];

    private const array LOCALIZED = [
        'entity' => ['جمعية الأمل الخيرية للتنمية الأسرية', 'Al-Amal Family Development Charity'],
        'cycle' => ['دورة جائزة التميز 2026', 'Excellence Award 2026 cycle'],
        'assessor' => ['خالد المطيري', 'Khalid Al-Mutairi'],
        'replaced' => ['نورة القحطاني', 'Noura Al-Qahtani'],
        'region' => ['منطقة الرياض', 'Riyadh Region'],
        'name' => ['الحوكمة والقيادة', 'Governance and leadership'],
        'page' => ['الصفحة الرئيسية', 'Home page'],
        'content' => ['خبر إطلاق دورة الجائزة', 'The award cycle launch news item'],
        'reason' => ['أُعيد فتح التقييم لمراجعة شواهد المعيار الفرعي 2.3.', 'The assessment was reopened to review the evidence of sub-criterion 2.3.'],
        'bank' => ['بنك المثال (مثال)', 'Example Bank (example)'],
        'note' => ['يرجى مراجعة درجة المعيار الفرعي 2.3 في ضوء الشواهد المرفقة.', 'Please review the score of sub-criterion 2.3 against the attached evidence.'],
    ];

    private const array TRANSLATED = [
        'purpose' => 'finance.purposes.accredited',
        'type' => 'contact.enquiry_types.general',
        'scope' => 'notifications.criteria.scopes.new_only',
        'outcome' => 'notifications.assessments.accredited.ACC-07.outcomes.continue_as_submitted',
        'change' => 'finance.bank_account_changes.added',
    ];

    private const array REFERENCES = [
        'payments' => 'PAY-2026-000031',
        'contact' => 'CNT-2026-000045',
    ];

    private const array NUMBERS = [
        'advanced' => 'ADV-2026-000088',
        'award' => 'ADV-2026-000088',
        'SELF' => 'SLF-2026-000210',
    ];

    /**
     * @return array<string, LocalizedText|string>
     */
    public static function for(string $prefix): array
    {
        $values = [];

        foreach (self::placeholdersOf($prefix) as $placeholder) {
            $values[$placeholder] = self::value($placeholder, explode('.', $prefix));
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    private static function placeholdersOf(string $prefix): array
    {
        $texts = '';

        foreach (['ar', 'en'] as $locale) {
            foreach (['title', 'body'] as $part) {
                $texts .= ' '.trans($prefix.'.'.$part, [], $locale);
            }
        }

        preg_match_all('/:([a-z_]+)/', $texts, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  list<string>  $segments
     */
    private static function value(string $placeholder, array $segments): LocalizedText|string
    {
        return match (true) {
            $placeholder === 'reference' => self::bySegment(self::REFERENCES, $segments, 'ENT-000017'),
            $placeholder === 'number' => self::bySegment(self::NUMBERS, $segments, 'ACC-2026-000123'),
            $placeholder === 'amount' => self::amount(),
            isset(self::TEXTS[$placeholder]) => self::TEXTS[$placeholder],
            isset(self::LOCALIZED[$placeholder]) => new LocalizedText(...self::LOCALIZED[$placeholder]),
            isset(self::TRANSLATED[$placeholder]) => LocalizedText::fromTranslation(self::TRANSLATED[$placeholder]),
            Lang::has('quantities.'.$placeholder, 'ar') => LocalizedText::fromChoice('quantities.'.$placeholder, self::SAMPLE_QUANTITY),
            default => throw MailSampleNotRendered::missingValue($placeholder),
        };
    }

    /**
     * @param  array<string, string>  $values
     * @param  list<string>  $segments
     */
    private static function bySegment(array $values, array $segments, string $default): string
    {
        foreach ($segments as $segment) {
            if (isset($values[$segment])) {
                return $values[$segment];
            }
        }

        return $default;
    }

    private static function amount(): LocalizedText
    {
        return LocalizedText::fromTranslationWith('finance.amount', [
            'amount' => '1725.00',
            'currency' => LocalizedText::fromTranslation('finance.currencies.SAR'),
        ]);
    }
}
