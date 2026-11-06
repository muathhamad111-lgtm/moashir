<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Insights\SaveMaturityText;
use App\Data\Insights\MaturityTextEdit;
use App\Enums\Insights\MaturityBand;
use App\Models\Identity\User;
use App\Support\ValueObjects\LocalizedText;

final readonly class DemoMaturityTextsInstaller
{
    private const array TEXTS = [
        'emerging' => [
            'ar' => 'الممارسات في بداياتها وتعتمد على اجتهادات فردية؛ ابدأ بتوثيق الأساسيات وتحديد المسؤوليات (نص تجريبي).',
            'en' => 'Practices are at an early stage and rely on individual effort; start by documenting the basics and assigning responsibilities (demo text).',
        ],
        'developing' => [
            'ar' => 'توجد ممارسات موثقة لكنها غير منتظمة التطبيق؛ ركّز على الاتساق والمتابعة الدورية (نص تجريبي).',
            'en' => 'Documented practices exist but are applied unevenly; focus on consistency and regular follow-up (demo text).',
        ],
        'mature' => [
            'ar' => 'الممارسات مطبقة بانتظام وتُقاس نتائجها؛ الخطوة التالية هي التحسين المستمر المبني على البيانات (نص تجريبي).',
            'en' => 'Practices are applied consistently and their results are measured; the next step is data-driven continuous improvement (demo text).',
        ],
        'leading' => [
            'ar' => 'الممارسات متقدمة ومتكاملة وتصلح نموذجًا يُحتذى في القطاع (نص تجريبي).',
            'en' => 'Practices are advanced and integrated and can serve as a model for the sector (demo text).',
        ],
    ];

    public function __construct(private SaveMaturityText $save) {}

    public function install(User $editor): void
    {
        foreach (self::TEXTS as $band => $text) {
            $this->save->handle($editor, new MaturityTextEdit(MaturityBand::from($band), null, new LocalizedText($text['ar'], $text['en'])));
        }
    }
}
