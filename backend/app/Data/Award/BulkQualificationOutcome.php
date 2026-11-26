<?php

declare(strict_types=1);

namespace App\Data\Award;

final readonly class BulkQualificationOutcome
{
    /**
     * @param  list<int>  $qualifiedIds
     * @param  list<array{id: int, reason: string}>  $skipped
     */
    public function __construct(public array $qualifiedIds, public array $skipped) {}
}
