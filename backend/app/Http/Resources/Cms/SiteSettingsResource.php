<?php

declare(strict_types=1);

namespace App\Http\Resources\Cms;

use App\Models\Cms\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SiteSettings $resource
 */
final class SiteSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = $this->resource;
        $contact = $settings->contact ?? [];

        return [
            'platform_name' => $settings->platform_name?->toArray(),
            'platform_description' => $settings->platform_description?->toArray(),
            'social' => $settings->social,
            'contact' => [
                'email' => $contact['email'] ?? null,
                'phone' => $contact['phone'] ?? null,
                'address' => $contact['address'] ?? null,
            ],
            'footer_note' => $settings->footer_note?->toArray(),
            'quick_links' => $settings->quick_links,
            'header_links' => $settings->header_links,
            'seo' => [
                'title' => $settings->seo['title'] ?? null,
                'description' => $settings->seo['description'] ?? null,
            ],
            'featured_kpi_limit' => $settings->featured_kpi_limit,
            'show_featured_kpis' => $settings->show_featured_kpis,
            'updated_at' => $settings->updated_at->toIso8601String(),
        ];
    }
}
