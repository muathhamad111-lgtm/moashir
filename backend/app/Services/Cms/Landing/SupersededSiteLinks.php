<?php

declare(strict_types=1);

namespace App\Services\Cms\Landing;

use App\Support\ScalarValue;

final class SupersededSiteLinks
{
    private const array HEADER_LINKS = [
        [['ar' => 'الرئيسية', 'en' => 'Home'], '/'],
        [['ar' => 'جولة في المنصة', 'en' => 'Platform tour'], '/#tour'],
        [['ar' => 'مسارات التقييم', 'en' => 'Assessment paths'], '/#paths'],
        [['ar' => 'نموذج التميّز', 'en' => 'Excellence model'], '/#model'],
        [['ar' => 'مؤشرات الأداء', 'en' => 'Performance Indicators'], '/kpis'],
        [['ar' => 'تواصل معنا', 'en' => 'Contact us'], '/#contact'],
    ];

    private const array QUICK_LINKS = [
        [['ar' => 'الرئيسية', 'en' => 'Home'], '/'],
        [['ar' => 'مسارات التقييم', 'en' => 'Assessment paths'], '#paths'],
        [['ar' => 'حقيبة التميز السريع', 'en' => 'Quick Excellence Kit'], '/bag'],
        [['ar' => 'مؤشرات الأداء', 'en' => 'Performance indicators'], '/kpis'],
        [['ar' => 'تواصل معنا', 'en' => 'Contact us'], '#contact'],
    ];

    /**
     * @param  array<array-key, mixed>  $links
     */
    public function isReplaceableHeader(array $links): bool
    {
        return $this->isReplaceable($links, self::HEADER_LINKS);
    }

    /**
     * @param  array<array-key, mixed>  $links
     */
    public function isReplaceableQuick(array $links): bool
    {
        return $this->isReplaceable($links, self::QUICK_LINKS);
    }

    /**
     * @param  array<array-key, mixed>  $links
     * @param  list<array{0: array{ar: string, en: string}, 1: string}>  $shipped
     */
    private function isReplaceable(array $links, array $shipped): bool
    {
        return $links === [] || $this->normalized($links) === $this->flattened($shipped);
    }

    /**
     * @param  array<array-key, mixed>  $links
     * @return list<string>
     */
    private function normalized(array $links): array
    {
        return array_values(array_map(static function (mixed $link): string {
            $link = is_array($link) ? $link : [];
            $label = is_array($link['label'] ?? null) ? $link['label'] : [];

            return implode('|', [ScalarValue::string($label['ar'] ?? null), ScalarValue::string($label['en'] ?? null), ScalarValue::string($link['url'] ?? null)]);
        }, $links));
    }

    /**
     * @param  list<array{0: array{ar: string, en: string}, 1: string}>  $shipped
     * @return list<string>
     */
    private function flattened(array $shipped): array
    {
        return array_map(static fn (array $link): string => implode('|', [$link[0]['ar'], $link[0]['en'], $link[1]]), $shipped);
    }
}
