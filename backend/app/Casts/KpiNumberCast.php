<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\ValueObjects\KpiNumber;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<KpiNumber|null, KpiNumber|string|null>
 */
final class KpiNumberCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?KpiNumber
    {
        return is_string($value) || is_int($value) ? KpiNumber::tryFrom((string) $value) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof KpiNumber => $value->value,
            is_string($value) => new KpiNumber($value)->value,
            default => throw new InvalidArgumentException('A KPI number must be a decimal string.'),
        };
    }
}
