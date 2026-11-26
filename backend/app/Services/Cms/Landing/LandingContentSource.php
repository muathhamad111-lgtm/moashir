<?php

declare(strict_types=1);

namespace App\Services\Cms\Landing;

use App\Data\Cms\LandingContent;
use RuntimeException;

final class LandingContentSource
{
    private const string PATH = 'data/content/landing-home.json';

    public function read(): LandingContent
    {
        $decoded = json_decode((string) file_get_contents(database_path(self::PATH)), true, 32, JSON_THROW_ON_ERROR);

        if (! is_array($decoded) || ! is_array($decoded['page'] ?? null)) {
            throw new RuntimeException('The landing content file has no page definition.');
        }

        return new LandingContent($decoded['page'], $this->listOf($decoded, 'header_links'), $this->listOf($decoded, 'quick_links'));
    }

    /**
     * @param  array<array-key, mixed>  $content
     * @return list<array<array-key, mixed>>
     */
    private function listOf(array $content, string $key): array
    {
        $items = $content[$key] ?? [];

        return is_array($items) ? array_values(array_filter($items, is_array(...))) : [];
    }
}
