<?php

declare(strict_types=1);

namespace App\Actions\Cms;

use App\Enums\Cms\CmsPageKey;
use App\Exceptions\Cms\CmsRejected;
use App\Services\Cms\PublishedPageReader;

final readonly class GetPublicPage
{
    public function __construct(private PublishedPageReader $pages) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(CmsPageKey $key): array
    {
        return $this->pages->read($key) ?? throw CmsRejected::pageNotPublished();
    }
}
