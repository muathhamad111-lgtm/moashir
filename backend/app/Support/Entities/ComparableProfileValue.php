<?php

declare(strict_types=1);

namespace App\Support\Entities;

use App\Support\ValueObjects\EntityName;
use BackedEnum;
use Carbon\CarbonInterface;

/**
 * A profile attribute reduced to a plain value that compares, stores as JSON and writes back through the model's casts.
 */
final class ComparableProfileValue
{
    public static function of(mixed $value): mixed
    {
        return match (true) {
            $value instanceof EntityName => $value->toArray(),
            $value instanceof BackedEnum => $value->value,
            $value instanceof CarbonInterface => $value->toDateString(),
            default => $value,
        };
    }
}
