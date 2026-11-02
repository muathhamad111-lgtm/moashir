<?php

declare(strict_types=1);

namespace App\Data\Entities;

use App\Enums\Entities\ErasureRetainedRecord;
use Carbon\CarbonImmutable;

final readonly class ErasureOutcome
{
    /**
     * @param  list<ErasureRetainedRecord>  $kept
     */
    public function __construct(
        public string $reference,
        public string $pseudonym,
        public CarbonImmutable $erasedAt,
        public array $kept,
    ) {}

    /**
     * @return array{reference: string, pseudonym: string, erased_at: string, kept: list<string>}
     */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'pseudonym' => $this->pseudonym,
            'erased_at' => $this->erasedAt->toIso8601String(),
            'kept' => array_map(static fn (ErasureRetainedRecord $record): string => $record->value, $this->kept),
        ];
    }
}
