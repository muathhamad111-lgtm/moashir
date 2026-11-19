<?php

declare(strict_types=1);

namespace App\Translation;

use App\Services\Notifications\Templates\NotificationTextOverrides;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;

/**
 * Lays the administrator's rewritten notification texts over the shipped `notifications` translation group,
 * so every builder that calls __('notifications.…') sends the current wording without knowing about it.
 */
final readonly class NotificationTextOverrideLoader implements Loader
{
    private const string GROUP = 'notifications';

    public function __construct(private Loader $files, private NotificationTextOverrides $overrides) {}

    /**
     * @return array<array-key, mixed>
     */
    public function load($locale, $group, $namespace = null): array
    {
        $loaded = $this->files->load($locale, $group, $namespace);
        $lines = is_array($loaded) ? $loaded : [];

        if ($group !== self::GROUP || ! in_array($namespace, [null, '*'], true)) {
            return $lines;
        }

        foreach ($this->overrides->all() as $key => $text) {
            $value = trim($text[$locale === 'en' ? 'en' : 'ar'] ?? '');

            if ($value !== '') {
                Arr::set($lines, $key, $value);
            }
        }

        return $lines;
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->files->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->files->addJsonPath($path);
    }

    /**
     * @return array<string, string>
     */
    public function namespaces(): array
    {
        return $this->files->namespaces();
    }
}
