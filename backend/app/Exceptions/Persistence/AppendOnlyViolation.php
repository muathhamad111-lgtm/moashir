<?php

declare(strict_types=1);

namespace App\Exceptions\Persistence;

use LogicException;

final class AppendOnlyViolation extends LogicException
{
    public static function forModel(string $modelClass): self
    {
        return new self(sprintf('%s records are append-only and cannot be changed or deleted.', $modelClass));
    }
}
