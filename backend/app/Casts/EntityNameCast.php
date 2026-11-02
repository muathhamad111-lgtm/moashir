<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\ValueObjects\EntityName;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<EntityName, EntityName|array<string, string|null>>
 */
final class EntityNameCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): EntityName
    {
        $decoded = is_string($value) ? json_decode($value, true, 4, JSON_THROW_ON_ERROR) : null;

        if (! is_array($decoded)) {
            throw new InvalidArgumentException(sprintf('The [%s] entity name is missing.', $key));
        }

        return EntityName::fromArray($decoded);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $name = match (true) {
            $value instanceof EntityName => $value,
            is_array($value) => EntityName::fromArray($value),
            default => throw new InvalidArgumentException('An entity name must be an array or EntityName.'),
        };

        return json_encode($name->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
