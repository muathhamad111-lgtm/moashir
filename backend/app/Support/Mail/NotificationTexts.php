<?php

declare(strict_types=1);

namespace App\Support\Mail;

use App\Enums\Notifications\NotificationCode;

final class NotificationTexts
{
    private const string ROOT = 'notifications';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $tree = trans(self::ROOT, [], 'ar');

        return is_array($tree) ? self::collect($tree, self::ROOT) : [];
    }

    /**
     * @return list<string>
     */
    public static function forCode(NotificationCode $code): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (string $prefix): bool => in_array($code->value, explode('.', $prefix), true),
        ));
    }

    /**
     * @param  array<array-key, mixed>  $node
     * @return list<string>
     */
    private static function collect(array $node, string $path): array
    {
        if (is_string($node['title'] ?? null) && is_string($node['body'] ?? null)) {
            return [$path];
        }

        $prefixes = [];

        foreach ($node as $key => $child) {
            if (is_array($child)) {
                $prefixes = [...$prefixes, ...self::collect($child, $path.'.'.$key)];
            }
        }

        return $prefixes;
    }
}
