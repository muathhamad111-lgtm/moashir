<?php

declare(strict_types=1);

namespace App\Data\Finance;

final readonly class BulkDecisionOutcome
{
    /**
     * @param  list<string>  $decided
     * @param  list<array{id: string, code: string}>  $skipped
     */
    public function __construct(public array $decided, public array $skipped) {}

    /**
     * @return array{decided: list<string>, skipped: list<array{id: string, code: string}>}
     */
    public function toArray(): array
    {
        return ['decided' => $this->decided, 'skipped' => $this->skipped];
    }
}
