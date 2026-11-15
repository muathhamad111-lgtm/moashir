<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Models\Cms\SiteSettings;
use App\Models\Settings\BrandingTheme;
use App\Support\Files\PublicAssetLink;
use App\Support\ValueObjects\LocalizedContent;

final readonly class PublicSiteSettings
{
    public function __construct(private PublicContentCache $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        return $this->cache->siteSettings(fn (): array => [...$this->present(SiteSettings::current()), 'branding' => $this->branding(BrandingTheme::current())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SiteSettings $settings): array
    {
        $contact = $settings->contact ?? [];

        return [
            'platform_name' => $settings->platform_name?->withFallback(),
            'platform_description' => $settings->platform_description?->withFallback(),
            'social' => $settings->social,
            'contact' => [
                'email' => $contact['email'] ?? null,
                'phone' => $contact['phone'] ?? null,
                'address' => $this->text($contact['address'] ?? null),
            ],
            'footer_note' => $settings->footer_note?->withFallback(),
            'quick_links' => $this->links($settings->quick_links),
            'header_links' => $this->links($settings->header_links),
            'seo' => [
                'title' => $this->text($settings->seo['title'] ?? null),
                'description' => $this->text($settings->seo['description'] ?? null),
            ],
            'show_featured_kpis' => $settings->show_featured_kpis,
            'featured_kpi_limit' => $settings->featured_kpi_limit,
        ];
    }

    /**
     * @return array{site_logo: array{file_id: string, url: string}|null, favicon: array{file_id: string, url: string}|null}
     */
    private function branding(BrandingTheme $theme): array
    {
        return ['site_logo' => PublicAssetLink::of($theme->siteLogo), 'favicon' => PublicAssetLink::of($theme->favicon)];
    }

    /**
     * @param  list<array{label: array<string, string>, url: string}>  $links
     * @return list<array{label: array{ar: string, en: string}|null, url: string}>
     */
    private function links(array $links): array
    {
        return array_map(fn (array $link): array => ['label' => $this->text($link['label']), 'url' => $link['url']], $links);
    }

    /**
     * @return array{ar: string, en: string}|null
     */
    private function text(mixed $value): ?array
    {
        return is_array($value) ? LocalizedContent::tryFromArray($value)?->withFallback() : null;
    }
}
