<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

final readonly class StorageQuota
{
    private const int BYTES_PER_GIB = 1024 * 1024 * 1024;

    private const int WHOLE_PERCENT = 100;

    public function __construct(public int $gigabytes, public int $warningPercent, public int $criticalPercent)
    {
        if ($gigabytes < 1) {
            throw new InvalidArgumentException('A storage quota holds at least one GiB.');
        }

        if ($warningPercent < 1 || $warningPercent >= $criticalPercent || $criticalPercent > self::WHOLE_PERCENT) {
            throw new InvalidArgumentException('The storage warning share must sit below the critical share, both within 1 to 100 percent.');
        }
    }

    public function bytes(): int
    {
        return $this->gigabytes * self::BYTES_PER_GIB;
    }
}
