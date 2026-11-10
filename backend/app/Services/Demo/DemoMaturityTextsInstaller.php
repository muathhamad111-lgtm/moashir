<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Actions\Insights\SaveMaturityText;
use App\Data\Insights\MaturityTextEdit;
use App\Enums\Insights\MaturityBand;
use App\Models\Identity\User;
use App\Models\Insights\MaturityInterpretation;
use App\Services\Settings\SettingsLock;
use App\Support\ValueObjects\LocalizedText;

final readonly class DemoMaturityTextsInstaller
{
    private const array TEXTS = [
        'emerging' => [
            'ar' => 'الممارسات في بداياتها وتعتمد على اجتهادات فردية؛ ابدأ بتوثيق الأساسيات وتحديد المسؤوليات.',
            'en' => 'Practices are at an early stage and rely on individual effort; start by documenting the basics and assigning responsibilities.',
        ],
        'developing' => [
            'ar' => 'توجد ممارسات موثقة لكنها غير منتظمة التطبيق؛ ركّز على الاتساق والمتابعة الدورية.',
            'en' => 'Documented practices exist but are applied unevenly; focus on consistency and regular follow-up.',
        ],
        'mature' => [
            'ar' => 'الممارسات مطبقة بانتظام وتُقاس نتائجها؛ الخطوة التالية هي التحسين المستمر المبني على البيانات.',
            'en' => 'Practices are applied consistently and their results are measured; the next step is data-driven continuous improvement.',
        ],
        'leading' => [
            'ar' => 'الممارسات متقدمة ومتكاملة وتصلح نموذجًا يُحتذى في القطاع.',
            'en' => 'Practices are advanced and integrated and can serve as a model for the sector.',
        ],
    ];

    public function __construct(private SaveMaturityText $save, private SettingsLock $lock) {}

    public function install(User $editor): int
    {
        $this->lock->lock(SaveMaturityText::LOCK);
        $emptyBands = array_diff_key(self::TEXTS, array_flip($this->bandsWithGenericText()));

        foreach ($emptyBands as $band => $text) {
            $this->save->handle($editor, new MaturityTextEdit(MaturityBand::from($band), null, new LocalizedText($text['ar'], $text['en'])));
        }

        return count($emptyBands);
    }

    /**
     * @return list<string>
     */
    private function bandsWithGenericText(): array
    {
        return array_values(MaturityInterpretation::query()
            ->whereNull('main_criterion_code')
            ->whereNotNull('text')
            ->get(['band'])
            ->map(static fn (MaturityInterpretation $row): string => $row->band->value)
            ->all());
    }
}
