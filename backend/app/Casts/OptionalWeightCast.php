<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\ValueObjects\Weight;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<Weight|null, Weight|null>
 */
final class OptionalWeightCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Weight
    {
        return is_string($value) || is_int($value) || is_float($value) ? Weight::of($value) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value !== null && ! $value instanceof Weight) {
            throw new InvalidArgumentException('An optional weight attribute must be set with a Weight value or null.');
        }

        return $value?->toDecimal();
    }
}
