<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\Cms\SiteSettings;
use App\Support\ValueObjects\LocalizedContent;

final readonly class PublicSiteSettings
{
    public function __construct(private PublicContentCache $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        return $this->cache->siteSettings(fn (): array => $this->present(SiteSettings::current()));
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SiteSettings $settings): array
    {
        $contact = $settings->contact ?? [];

        return [
            'social' => $settings->social,
            'contact' => [
                'email' => $contact['email'] ?? null,
                'phone' => $contact['phone'] ?? null,
                'address' => $this->text($contact['address'] ?? null),
            ],
            'footer_note' => $settings->footer_note?->withFallback(),
            'quick_links' => array_map(fn (array $link): array => ['label' => $this->text($link['label']), 'url' => $link['url']], $settings->quick_links),
            'seo' => [
                'title' => $this->text($settings->seo['title'] ?? null),
                'description' => $this->text($settings->seo['description'] ?? null),
            ],
            'show_featured_kpis' => $settings->show_featured_kpis,
            'featured_kpi_limit' => $settings->featured_kpi_limit,
        ];
    }

    /**
     * @return array{ar: string, en: string}|null
     */
    private function text(mixed $value): ?array
    {
        return is_array($value) ? LocalizedContent::tryFromArray($value)?->withFallback() : null;
    }
}
