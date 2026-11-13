<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Enums\Cms\CmsPageKey;
use App\Enums\Identity\TermsKind;
use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Config\Repository as Config;

final readonly class PublicContentCache
{
    private const string PREFIX = 'cms:public:';

    private const string SITE_SETTINGS = 'site-settings';

    public function __construct(private Repository $cache, private Config $config) {}

    /**
     * @param  Closure(): (array<string, mixed>|null)  $build
     * @return array<string, mixed>|null
     */
    public function page(CmsPageKey $key, Closure $build): ?array
    {
        return $this->remember('page:'.$key->value, $build);
    }

    /**
     * @param  Closure(): (array<string, mixed>|null)  $build
     * @return array<string, mixed>|null
     */
    public function legalText(TermsKind $kind, Closure $build): ?array
    {
        return $this->remember('legal:'.$kind->value, $build);
    }

    /**
     * @param  Closure(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public function siteSettings(Closure $build): array
    {
        return $this->remember(self::SITE_SETTINGS, $build) ?? [];
    }

    public function forgetPage(CmsPageKey $key): void
    {
        $this->cache->forget(self::PREFIX.'page:'.$key->value);
    }

    public function forgetLegalText(TermsKind $kind): void
    {
        $this->cache->forget(self::PREFIX.'legal:'.$kind->value);
    }

    public function forgetSiteSettings(): void
    {
        $this->cache->forget(self::PREFIX.self::SITE_SETTINGS);
    }

    /**
     * @param  Closure(): (array<string, mixed>|null)  $build
     * @return array<string, mixed>|null
     */
    private function remember(string $key, Closure $build): ?array
    {
        $cached = $this->cache->remember(
            self::PREFIX.$key,
            $this->config->integer('moashir.cms.public_cache_seconds'),
            static fn (): array => ['payload' => $build()],
        );

        if (! is_array($cached) || ! is_array($cached['payload'] ?? null)) {
            return null;
        }

        $payload = [];

        foreach ($cached['payload'] as $field => $value) {
            $payload[(string) $field] = $value;
        }

        return $payload;
    }
}
