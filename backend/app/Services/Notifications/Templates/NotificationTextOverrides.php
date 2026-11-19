<?php

declare(strict_types=1);

namespace App\Services\Notifications\Templates;

use App\Models\Notifications\NotificationTextOverride;
use Illuminate\Contracts\Cache\Repository;

/**
 * The texts an administrator rewrote, keyed by their full translation key inside the `notifications` group.
 */
final readonly class NotificationTextOverrides
{
    public const string CACHE_KEY = 'notifications.text-overrides';

    public function __construct(private Repository $cache) {}

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public function all(): array
    {
        /** @var array<string, array{ar: string, en: string}> */
        return $this->cache->rememberForever(self::CACHE_KEY, static function (): array {
            $texts = [];

            foreach (NotificationTextOverride::query()->get(['template_key', 'parts']) as $override) {
                foreach ($override->parts as $part => $text) {
                    $texts[$override->template_key.'.'.$part] = $text;
                }
            }

            return $texts;
        });
    }

    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }
}
