<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\ValueObjects\Percent;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<Percent|null, Percent|null>
 */
final class OptionalPercentCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Percent
    {
        return is_string($value) || is_int($value) ? Percent::of((string) $value) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value !== null && ! $value instanceof Percent) {
            throw new InvalidArgumentException('An optional percentage attribute must be set with a Percent value or null.');
        }

        return $value?->toDecimal();
    }
}
