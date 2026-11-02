<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Identity\PublishTermsVersion;
use App\Data\Identity\TermsPublication;
use App\Enums\Identity\TermsKind;
use App\Models\Identity\TermsVersion;
use App\Models\Identity\User;
use App\Support\ValueObjects\LocalizedText;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

final class LocalTermsSeeder extends Seeder
{
    private const string PUBLISHER_EMAIL = 'system.admin@moashir.example.sa';

    private const array TEXTS = [
        'terms' => [
            'ar' => 'نسخة تجريبية من الشروط والأحكام لبيئة التطوير المحلية فقط. تنشر إدارة المنصة النص المعتمد في بيئتي الاختبار والتشغيل.',
            'en' => 'Sample terms and conditions for the local development environment only. The platform administration publishes the approved text in staging and production.',
        ],
        'privacy' => [
            'ar' => 'نسخة تجريبية من سياسة الخصوصية لبيئة التطوير المحلية فقط. تنشر إدارة المنصة النص المعتمد في بيئتي الاختبار والتشغيل.',
            'en' => 'Sample privacy policy for the local development environment only. The platform administration publishes the approved text in staging and production.',
        ],
    ];

    public function run(PublishTermsVersion $publishTerms): void
    {
        $publisher = User::query()->where('email', self::PUBLISHER_EMAIL)->first();

        if (! $publisher instanceof User) {
            return;
        }

        foreach (TermsKind::cases() as $kind) {
            if (TermsVersion::query()->current()->where('kind', $kind->value)->exists()) {
                continue;
            }

            $publishTerms->handle($publisher, new TermsPublication(
                $kind,
                'local-1',
                LocalizedText::fromArray(self::TEXTS[$kind->value]),
                CarbonImmutable::today(),
            ));
        }
    }
}
