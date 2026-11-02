<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\ValueObjects\EarnedScore;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<EarnedScore, EarnedScore>
 */
final class EarnedScoreCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): EarnedScore
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new InvalidArgumentException(sprintf('The [%s] earned score is missing.', $key));
        }

        return EarnedScore::of((string) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (! $value instanceof EarnedScore) {
            throw new InvalidArgumentException('An earned score attribute must be set with an EarnedScore value.');
        }

        return $value->toDecimal();
    }
}
