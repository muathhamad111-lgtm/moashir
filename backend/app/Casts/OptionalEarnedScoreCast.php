<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\ValueObjects\EarnedScore;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<EarnedScore|null, EarnedScore|null>
 */
final class OptionalEarnedScoreCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?EarnedScore
    {
        return is_string($value) || is_int($value) ? EarnedScore::of((string) $value) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value !== null && ! $value instanceof EarnedScore) {
            throw new InvalidArgumentException('An optional earned score attribute must be set with an EarnedScore value or null.');
        }

        return $value?->toDecimal();
    }
}
