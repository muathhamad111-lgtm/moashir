<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\ValueObjects\Weight;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<Weight, Weight>
 */
final class WeightCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Weight
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException(sprintf('The [%s] weight is missing.', $key));
        }

        return Weight::of($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (! $value instanceof Weight) {
            throw new InvalidArgumentException('A weight attribute must be set with a Weight value.');
        }

        return $value->toDecimal();
    }
}
