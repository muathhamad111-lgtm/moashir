<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Enums\Cms\CmsPageKey;
use App\Services\Cms\PublishedPageReader;

final readonly class GetPublicSiteStatus
{
    public function __construct(private PublishedPageReader $pages) {}

    /**
     * @return array{maintenance: bool, published_at: mixed}
     */
    public function handle(): array
    {
        $home = $this->pages->read(CmsPageKey::Home);

        return ['maintenance' => $home === null, 'published_at' => $home['published_at'] ?? null];
    }
}
