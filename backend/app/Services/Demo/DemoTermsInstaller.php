<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Identity\PublishTermsVersion;
use App\Data\Identity\TermsPublication;
use App\Enums\Identity\TermsKind;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Support\ValueObjects\LocalizedText;
use Carbon\CarbonImmutable;

final readonly class DemoTermsInstaller
{
    private const string VERSION_LABEL = 'demo-1';

    private const array TEXTS = [
        'terms' => [
            'ar' => 'شروط وأحكام تجريبية لبيئة العرض فقط، وجميع البيانات المعروضة فيها بيانات افتراضية. تنشر إدارة المنصة النص المعتمد قبل التشغيل الفعلي.',
            'en' => 'Demonstration terms and conditions for the review environment only; every record shown here is fictional. The platform administration publishes the approved text before go-live.',
        ],
        'privacy' => [
            'ar' => 'سياسة خصوصية تجريبية لبيئة العرض فقط، ولا تُجمع فيها أي بيانات شخصية حقيقية. تنشر إدارة المنصة النص المعتمد قبل التشغيل الفعلي.',
            'en' => 'Demonstration privacy policy for the review environment only; no real personal data is collected here. The platform administration publishes the approved text before go-live.',
        ],
    ];

    public function __construct(private PublishTermsVersion $publishTerms) {}

    /**
     * @return list<int>
     */
    public function install(User $publisher): array
    {
        $currentIds = [];

        foreach (TermsKind::cases() as $kind) {
            $current = TermsVersion::query()->current()->where('kind', $kind->value)->first()
                ?? $this->publishTerms->handle($publisher, new TermsPublication(
                    $kind,
                    self::VERSION_LABEL,
                    LocalizedText::fromArray(self::TEXTS[$kind->value]),
                    CarbonImmutable::today(),
                ));
            $currentIds[] = $current->id;
        }

        return $currentIds;
    }
}
