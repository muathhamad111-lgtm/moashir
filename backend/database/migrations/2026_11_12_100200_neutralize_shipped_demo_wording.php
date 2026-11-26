<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array MATURITY_TEXTS = [
        'emerging' => [
            ['ar' => 'الممارسات في بداياتها وتعتمد على اجتهادات فردية؛ ابدأ بتوثيق الأساسيات وتحديد المسؤوليات (نص تجريبي).', 'en' => 'Practices are at an early stage and rely on individual effort; start by documenting the basics and assigning responsibilities (demo text).'],
            ['ar' => 'الممارسات في بداياتها وتعتمد على اجتهادات فردية؛ ابدأ بتوثيق الأساسيات وتحديد المسؤوليات.', 'en' => 'Practices are at an early stage and rely on individual effort; start by documenting the basics and assigning responsibilities.'],
        ],
        'developing' => [
            ['ar' => 'توجد ممارسات موثقة لكنها غير منتظمة التطبيق؛ ركّز على الاتساق والمتابعة الدورية (نص تجريبي).', 'en' => 'Documented practices exist but are applied unevenly; focus on consistency and regular follow-up (demo text).'],
            ['ar' => 'توجد ممارسات موثقة لكنها غير منتظمة التطبيق؛ ركّز على الاتساق والمتابعة الدورية.', 'en' => 'Documented practices exist but are applied unevenly; focus on consistency and regular follow-up.'],
        ],
        'mature' => [
            ['ar' => 'الممارسات مطبقة بانتظام وتُقاس نتائجها؛ الخطوة التالية هي التحسين المستمر المبني على البيانات (نص تجريبي).', 'en' => 'Practices are applied consistently and their results are measured; the next step is data-driven continuous improvement (demo text).'],
            ['ar' => 'الممارسات مطبقة بانتظام وتُقاس نتائجها؛ الخطوة التالية هي التحسين المستمر المبني على البيانات.', 'en' => 'Practices are applied consistently and their results are measured; the next step is data-driven continuous improvement.'],
        ],
        'leading' => [
            ['ar' => 'الممارسات متقدمة ومتكاملة وتصلح نموذجًا يُحتذى في القطاع (نص تجريبي).', 'en' => 'Practices are advanced and integrated and can serve as a model for the sector (demo text).'],
            ['ar' => 'الممارسات متقدمة ومتكاملة وتصلح نموذجًا يُحتذى في القطاع.', 'en' => 'Practices are advanced and integrated and can serve as a model for the sector.'],
        ],
    ];

    private const array CYCLE_NAME = [
        ['ar' => 'الدورة التجريبية لجائزة التميز المؤسسي', 'en' => 'Institutional Excellence Award - demonstration cycle'],
        ['ar' => 'دورة جائزة التميز المؤسسي', 'en' => 'Institutional Excellence Award cycle'],
    ];

    private const array HERO_CAPTION = [
        ['ar' => 'مثال توضيحي لنتيجة جهة افتراضية', 'en' => 'Illustrative result of a sample entity'],
        ['ar' => 'النتيجة حسب المعايير الرئيسية', 'en' => 'Result by main criterion'],
    ];

    private const string HERO_TYPE = 'hero';

    public function up(): void
    {
        $this->replaceShippedTexts(0, 1);
    }

    public function down(): void
    {
        $this->replaceShippedTexts(1, 0);
    }

    private function replaceShippedTexts(int $from, int $to): void
    {
        foreach (self::MATURITY_TEXTS as $band => $texts) {
            DB::table('maturity_interpretations')
                ->where('band', $band)
                ->whereNull('main_criterion_code')
                ->whereRaw('text = ?::jsonb', [$this->json($texts[$from])])
                ->update(['text' => $this->json($texts[$to])]);
        }

        DB::table('award_cycles')
            ->whereRaw('name = ?::jsonb', [$this->json(self::CYCLE_NAME[$from])])
            ->update(['name' => $this->json(self::CYCLE_NAME[$to])]);

        $this->replaceHeroCaption(self::HERO_CAPTION[$from], self::HERO_CAPTION[$to]);
    }

    /**
     * @param  array{ar: string, en: string}  $from
     * @param  array{ar: string, en: string}  $to
     */
    private function replaceHeroCaption(array $from, array $to): void
    {
        DB::table('cms_sections')
            ->where('type', self::HERO_TYPE)
            ->whereRaw("meta #> '{preview,caption}' = ?::jsonb", [$this->json($from)])
            ->update(['meta->preview->caption' => $to]);

        foreach (DB::table('cms_pages')->whereNotNull('published_content')->pluck('published_content', 'id') as $pageId => $published) {
            $this->replaceHeroCaptionInPublishedPage((int) $pageId, $published, [$from, $to]);
        }
    }

    /**
     * @param  array{array{ar: string, en: string}, array{ar: string, en: string}}  $captions
     */
    private function replaceHeroCaptionInPublishedPage(int $pageId, mixed $published, array $captions): void
    {
        $content = json_decode(is_string($published) ? $published : '', true);

        if (! is_array($content) || ! is_array($content['sections'] ?? null)) {
            return;
        }

        $sections = array_map(fn (mixed $section): mixed => $this->withHeroCaption($section, ...$captions), $content['sections']);

        if ($sections !== $content['sections']) {
            DB::table('cms_pages')->where('id', $pageId)->update([
                'published_content' => json_encode([...$content, 'sections' => $sections], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ]);
        }
    }

    /**
     * @param  array{ar: string, en: string}  $from
     * @param  array{ar: string, en: string}  $to
     */
    private function withHeroCaption(mixed $section, array $from, array $to): mixed
    {
        $meta = is_array($section) && ($section['type'] ?? null) === self::HERO_TYPE ? $section['meta'] ?? null : null;
        $preview = is_array($meta) ? $meta['preview'] ?? null : null;

        if (! is_array($section) || ! is_array($meta) || ! is_array($preview) || ($preview['caption'] ?? null) !== $from) {
            return $section;
        }

        return [...$section, 'meta' => [...$meta, 'preview' => [...$preview, 'caption' => $to]]];
    }

    /**
     * @param  array{ar: string, en: string}  $text
     */
    private function json(array $text): string
    {
        return json_encode($text, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
};
