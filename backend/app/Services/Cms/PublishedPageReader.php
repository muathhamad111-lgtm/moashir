<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Enums\Cms\CmsPageKey;
use App\Models\Cms\CmsPage;

final readonly class PublishedPageReader
{
    public function __construct(private PublicContentCache $cache, private PublishedPagePayload $payload) {}

    /**
     * @return array<string, mixed>|null
     */
    public function read(CmsPageKey $key): ?array
    {
        return $this->cache->page($key, function () use ($key): ?array {
            $page = CmsPage::query()->where('key', $key)->first();

            if (! $page instanceof CmsPage || ! $page->is_published || ! is_array($page->published_content)) {
                return null;
            }

            return $this->payload->of($page, $page->published_content);
        });
    }
}
