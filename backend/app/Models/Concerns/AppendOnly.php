<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\Persistence\AppendOnlyViolation;

trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(static fn (): never => throw AppendOnlyViolation::forModel(static::class));
        static::deleting(static fn (): never => throw AppendOnlyViolation::forModel(static::class));
    }
}
