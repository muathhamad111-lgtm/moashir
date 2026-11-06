<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\ValueObjects\LocalizedContent;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<LocalizedContent|null, LocalizedContent|array<string, mixed>|null>
 */
final class LocalizedContentCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?LocalizedContent
    {
        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true, 4, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? LocalizedContent::tryFromArray($decoded) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $content = match (true) {
            $value === null => null,
            $value instanceof LocalizedContent => $value,
            is_array($value) => LocalizedContent::tryFromArray($value),
            default => throw new InvalidArgumentException('Localized content must be an array or LocalizedContent.'),
        };

        return $content instanceof LocalizedContent ? json_encode($content->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null;
    }
}
