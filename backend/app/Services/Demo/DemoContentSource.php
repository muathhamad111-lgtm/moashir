<?php

declare(strict_types=1);

namespace App\Services\Demo;

use RuntimeException;

final class DemoContentSource
{
    private const string PATH = 'data/demo/public-content.json';

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        $decoded = json_decode((string) file_get_contents(database_path(self::PATH)), true, 32, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('The demo content file is not a JSON object.');
        }

        $content = [];

        foreach ($decoded as $key => $value) {
            $content[(string) $key] = $value;
        }

        return $content;
    }

    /**
     * @param  array<array-key, mixed>  $content
     * @return list<array<array-key, mixed>>
     */
    public static function listOf(array $content, string $key): array
    {
        $items = $content[$key] ?? [];

        return is_array($items) ? array_values(array_filter($items, is_array(...))) : [];
    }
}
