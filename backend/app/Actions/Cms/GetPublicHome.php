<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Enums\Cms\CmsPageKey;
use App\Services\Cms\PublicSiteSettings;
use App\Services\Cms\PublishedPageReader;

final readonly class GetPublicHome
{
    public function __construct(private PublishedPageReader $pages, private PublicSiteSettings $siteSettings) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $home = $this->pages->read(CmsPageKey::Home);

        if ($home === null) {
            return ['published' => false, 'maintenance' => true];
        }

        return [
            'published' => true,
            'maintenance' => false,
            'title' => $home['title'] ?? null,
            'seo' => $home['seo'] ?? null,
            'sections' => $home['sections'] ?? [],
            'site_settings' => $this->siteSettings->read(),
            'published_at' => $home['published_at'] ?? null,
        ];
    }
}
