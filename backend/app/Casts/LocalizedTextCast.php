<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\ValueObjects\LocalizedText;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<LocalizedText|null, LocalizedText|array<string, string>|null>
 */
final class LocalizedTextCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?LocalizedText
    {
        if (! is_string($value)) {
            return null;
        }

        $decoded = json_decode($value, true, 4, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? LocalizedText::fromArray($decoded) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = match (true) {
            $value instanceof LocalizedText => $value,
            is_array($value) => LocalizedText::fromArray($value),
            default => throw new InvalidArgumentException('Localized text must be an array or LocalizedText.'),
        };

        return json_encode($text->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
