<?php

declare(strict_types=1);

namespace App\Data\Finance;

final readonly class TransferPromotionResult
{
    /**
     * @param  list<string>  $promoted  references of the payments made live
     * @param  array<string, string>  $failed  reference => error code of the payments left unchanged
     */
    public function __construct(public array $promoted, public array $failed) {}

    public function hasFailures(): bool
    {
        return $this->failed !== [];
    }
}
