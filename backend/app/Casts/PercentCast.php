<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\ValueObjects\Percent;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<Percent, Percent>
 */
final class PercentCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Percent
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException(sprintf('The [%s] percentage is missing.', $key));
        }

        return Percent::of((string) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (! $value instanceof Percent) {
            throw new InvalidArgumentException('A percentage attribute must be set with a Percent value.');
        }

        return $value->toDecimal();
    }
}
